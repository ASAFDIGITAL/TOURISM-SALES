<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Livewire\AgentSupportWidget;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_opens_ticket_via_floating_widget_and_chats_with_admin(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $tenant = Tenant::create([
            'name' => 'Sky Travel',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $agent = User::create([
            'name' => 'Dana Agent',
            'email' => 'dana@sky.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        // 1. Floating widget does not render on login page, but renders on agent panel
        $this->get('/agent/login')
            ->assertOk()
            ->assertDontSeeLivewire(AgentSupportWidget::class);

        $this->actingAs($agent)
            ->get('/agent')
            ->assertOk()
            ->assertSeeLivewire(AgentSupportWidget::class);

        // 2. Agent opens a new support ticket from floating widget
        Livewire::actingAs($agent)
            ->test(AgentSupportWidget::class)
            ->call('toggleOpen')
            ->set('subject', 'בעיה בהפקת קבלה')
            ->set('category', SupportTicket::CATEGORY_TECHNICAL)
            ->set('priority', SupportTicket::PRIORITY_HIGH)
            ->set('initialMessage', 'שלום, לא מצליחה להפיק קבלה לטיול #12')
            ->call('createTicket')
            ->assertSet('view', 'chat')
            ->assertHasNoErrors();

        $ticket = SupportTicket::first();
        $this->assertNotNull($ticket);
        $this->assertSame($tenant->id, $ticket->tenant_id);
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertSame(1, $ticket->admin_unread_count);
        $this->assertSame(0, $ticket->agent_unread_count);
        $this->assertCount(1, $ticket->messages);

        // Admin received DB notification
        $this->assertSame(1, $admin->notifications()->count());

        // 3. Admin views list & opens chat page
        session()->flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($admin)
            ->get(SupportTicketResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSee('בעיה בהפקת קבלה');

        Livewire::actingAs($admin)
            ->test(ViewSupportTicket::class, ['record' => $ticket->getRouteKey()])
            ->assertSee('שלום, לא מצליחה להפיק קבלה לטיול #12')
            ->set('replyBody', 'היי דנה, בדקתי וסידרתי את ההגדרה. נסי עכשיו!')
            ->call('sendReply')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(0, $ticket->admin_unread_count);
        $this->assertSame(1, $ticket->agent_unread_count);
        $this->assertSame(SupportTicket::STATUS_WAITING_AGENT, $ticket->status);
        $this->assertCount(2, $ticket->messages);

        // Agent received DB notification
        $this->assertSame(1, $agent->notifications()->count());

        // 4. Agent sees unread badge, opens chat (marks read), and sends reply
        session()->flush();
        Filament::setCurrentPanel(Filament::getPanel('agent'));

        Livewire::actingAs($agent)
            ->test(AgentSupportWidget::class)
            ->assertSee('1')
            ->call('openTicket', $ticket->id)
            ->assertSet('view', 'chat')
            ->assertSee('היי דנה, בדקתי וסידרתי את ההגדרה. נסי עכשיו!')
            ->set('replyBody', 'עובד מעולה, תודה רבה!')
            ->call('sendReply')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(0, $ticket->agent_unread_count);
        $this->assertSame(1, $ticket->admin_unread_count);
        $this->assertSame(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertCount(3, $ticket->messages);
    }

    public function test_agent_cannot_view_or_reply_to_other_tenant_ticket(): void
    {
        $tenantA = Tenant::create(['name' => 'Agency A', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Agency B', 'status' => 'active']);

        $agentA = User::create([
            'name' => 'Agent A',
            'email' => 'a@test.com',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenantA->id,
        ]);

        $agentB = User::create([
            'name' => 'Agent B',
            'email' => 'b@test.com',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenantB->id,
        ]);

        $ticketA = SupportTicket::create([
            'tenant_id' => $tenantA->id,
            'user_id' => $agentA->id,
            'subject' => 'Secret Ticket A',
            'status' => SupportTicket::STATUS_OPEN,
            'last_message_at' => now(),
        ]);

        SupportMessage::create([
            'support_ticket_id' => $ticketA->id,
            'user_id' => $agentA->id,
            'sender_type' => SupportMessage::SENDER_AGENT,
            'body' => 'Private info for A',
        ]);

        Livewire::actingAs($agentB)
            ->test(AgentSupportWidget::class)
            ->call('openTicket', $ticketA->id)
            ->assertSet('activeTicketId', null)
            ->assertDontSee('Private info for A');
    }
}
