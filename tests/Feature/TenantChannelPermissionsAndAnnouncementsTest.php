<?php

namespace Tests\Feature;

use App\Mail\TripMessageMail;
use App\Models\Customer;
use App\Models\ReminderRule;
use App\Models\SystemAnnouncement;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantChannelPermissionsAndAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndAgent(string $name, bool $remindersEnabled = true, ?array $allowedChannels = null): array
    {
        $tenant = Tenant::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'phone' => '050-1234567',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
            'reminders_enabled' => $remindersEnabled,
            'allowed_channels' => $allowedChannels,
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $tenant->id,
            'language' => 'he',
        ]);

        return [$tenant, $agent];
    }

    public function test_disabled_reminders_blocks_agent_pages_and_skips_rule_execution(): void
    {
        [$tenant, $agent] = $this->createTenantAndAgent('Closed Reminders', false, ['email']);

        $this->actingAs($agent)->get('/agent/reminders')->assertForbidden();
        $this->actingAs($agent)->get('/agent/reminder-rules')->assertForbidden();
    }

    public function test_email_only_channel_restriction_blocks_sms_dispatch(): void
    {
        Mail::fake();
        Http::fake();
        config()->set('services.inforu.basic_auth', 'dGVzdDp0b2tlbg==');

        [$tenant, $agent] = $this->createTenantAndAgent('Email Only Agency', true, ['email']);
        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Avi Cohen',
            'email' => 'avi@example.com',
            'phone' => '052-1112233',
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Berlin Trip',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'total_amount' => 3000,
            'status' => 'confirmed',
        ]);

        ReminderRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Rule',
            'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_TRIP,
            'days_offset' => 2,
            'recipient_type' => 'customer',
            'channel' => 'all',
            'template_type' => TripMessageService::TEMPLATE_PRE_TRIP_REMINDER,
            'message_language' => 'he',
            'is_active' => true,
        ]);

        $this->artisan('app:run-reminder-rules')->assertSuccessful();

        Mail::assertSent(TripMessageMail::class, 1);
        Http::assertNothingSent();
    }

    public function test_system_announcements_respect_target_and_validity_dates(): void
    {
        [$tenantA, $agentA] = $this->createTenantAndAgent('Agency A');
        [$tenantB, $agentB] = $this->createTenantAndAgent('Agency B');

        // 1. Global active announcement -> visible to BOTH A and B
        SystemAnnouncement::create([
            'title' => 'Global Active Notice',
            'body' => 'Maintenance tonight',
            'type' => 'warning',
            'target_type' => 'all',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);

        // 2. Targeted to user A only -> visible ONLY to A
        SystemAnnouncement::create([
            'title' => 'Personal Notice For A',
            'body' => 'Special message for A',
            'type' => 'info',
            'target_type' => 'user',
            'user_id' => $agentA->id,
            'is_active' => true,
        ]);

        // 3. Expired announcement -> visible to NO ONE
        SystemAnnouncement::create([
            'title' => 'Expired Notice',
            'body' => 'Old message',
            'type' => 'danger',
            'target_type' => 'all',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);

        // 4. Future scheduled announcement -> visible to NO ONE yet
        SystemAnnouncement::create([
            'title' => 'Future Scheduled Notice',
            'body' => 'Starts tomorrow',
            'type' => 'success',
            'target_type' => 'all',
            'starts_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $this->actingAs($agentA)->get('/agent')
            ->assertOk()
            ->assertSee('Global Active Notice')
            ->assertSee('Personal Notice For A')
            ->assertDontSee('Expired Notice')
            ->assertDontSee('Future Scheduled Notice');

        $this->actingAs($agentB)->get('/agent')
            ->assertOk()
            ->assertSee('Global Active Notice')
            ->assertDontSee('Personal Notice For A')
            ->assertDontSee('Expired Notice')
            ->assertDontSee('Future Scheduled Notice');
    }

    public function test_default_recommended_rules_only_use_tenants_allowed_channels(): void
    {
        [$emailOnlyTenant] = $this->createTenantAndAgent('Email Only Presets', true, ['email']);
        [$pushOnlyTenant] = $this->createTenantAndAgent('Push Only Presets', true, ['push']);
        [$waOnlyTenant] = $this->createTenantAndAgent('WhatsApp Only Presets', true, ['whatsapp']);

        $engine = app(\App\Services\ReminderRuleEngine::class);

        $this->assertSame(4, $engine->createDefaultRulesForTenant($emailOnlyTenant->id, 'he'));
        $emailRules = ReminderRule::withoutGlobalScopes()->where('tenant_id', $emailOnlyTenant->id)->get();
        $this->assertTrue($emailRules->every(fn ($r) => $r->channel === 'email' && $r->recipient_type === 'customer'));

        $this->assertSame(4, $engine->createDefaultRulesForTenant($pushOnlyTenant->id, 'he'));
        $pushRules = ReminderRule::withoutGlobalScopes()->where('tenant_id', $pushOnlyTenant->id)->get();
        $this->assertTrue($pushRules->every(fn ($r) => $r->channel === 'push' && $r->recipient_type === 'agent'));

        $this->assertSame(0, $engine->createDefaultRulesForTenant($waOnlyTenant->id, 'he'));
    }

    public function test_agent_without_sms_can_request_sms_access_and_admin_gets_notified(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'language' => 'he',
        ]);

        [$tenant, $agent] = $this->createTenantAndAgent('No SMS Agency', true, ['push', 'email']);

        $mockPush = \Mockery::mock(\App\Services\WebPushService::class);
        $mockPush->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function (User $u, string $title) use ($admin) {
                return $u->id === $admin->id && str_contains($title, 'No SMS Agency');
            })
            ->andReturn(1);
        $this->app->instance(\App\Services\WebPushService::class, $mockPush);

        $this->actingAs($agent);
        $sent = \App\Filament\Agent\Actions\RequestSmsAccessAction::dispatchRequest('רוצה חבילת SMS');

        $this->assertTrue($sent);
        $this->assertSame(1, $admin->notifications()->count());
        // Cooldown prevents duplicate spam within 10 minutes
        $this->assertFalse(\App\Filament\Agent\Actions\RequestSmsAccessAction::dispatchRequest());
    }
}
