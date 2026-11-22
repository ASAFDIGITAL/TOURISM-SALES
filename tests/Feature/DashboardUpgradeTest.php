<?php

namespace Tests\Feature;

use App\Filament\Agent\Resources\TripResource\Pages\CreateTrip;
use App\Filament\Agent\Widgets\AgentStatsOverview;
use App\Filament\Agent\Widgets\DashboardHeroWidget;
use App\Filament\Agent\Widgets\RevenueChartWidget;
use App\Filament\Agent\Widgets\TopDestinationsChartWidget;
use App\Filament\Agent\Widgets\TripsStatusChartWidget;
use App\Filament\Agent\Widgets\UpcomingDeparturesWidget;
use App\Filament\Widgets\AdminGrowthChartWidget;
use App\Filament\Widgets\AdminOverview;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_dashboard_renders_upgraded_crm_widgets_and_hides_heading(): void
    {
        $tenant = Tenant::create([
            'name' => 'Golden Tours',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $agent = User::create([
            'name' => 'Avi Agent',
            'email' => 'avi@golden.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Noa Cohen',
            'phone' => '0501234567',
        ]);

        $trip = Trip::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Rome Holiday',
            'destination' => 'רומא',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'total_amount' => 5000,
            'status' => 'confirmed',
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'amount' => 2000,
            'method' => 'credit_card',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($agent)->get('/agent');

        $response->assertOk()
            ->assertDontSee('<h1', false)
            ->assertDontSee('ui.sales_reports')
            ->assertDontSee('ui.smart_reminder_rules')
            ->assertDontSee('ui.mobile_app_notifications')
            ->assertSeeLivewire(DashboardHeroWidget::class)
            ->assertSeeLivewire(AgentStatsOverview::class)
            ->assertSeeLivewire(RevenueChartWidget::class)
            ->assertSeeLivewire(TripsStatusChartWidget::class)
            ->assertSeeLivewire(UpcomingDeparturesWidget::class)
            ->assertSeeLivewire(TopDestinationsChartWidget::class)
            ->assertSee('Golden Tours');

        Livewire::actingAs($agent)
            ->test(UpcomingDeparturesWidget::class)
            ->assertSee('רומא')
            ->assertSee('Noa Cohen');
    }

    public function test_admin_dashboard_renders_growth_chart_and_hides_heading(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk()
            ->assertDontSee('<h1', false)
            ->assertSeeLivewire(AdminOverview::class)
            ->assertSeeLivewire(AdminGrowthChartWidget::class);
    }

    public function test_agent_can_create_new_customer_inline_while_creating_trip(): void
    {
        $tenant = Tenant::create([
            'name' => 'Inline Tours',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $agent = User::create([
            'name' => 'Maya Agent',
            'email' => 'maya@inline.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('agent'));

        Livewire::actingAs($agent)
            ->test(CreateTrip::class)
            ->callFormComponentAction('customer_id', 'createOption', data: [
                'name' => 'דניאל לוי',
                'phone' => '0529876543',
                'email' => 'daniel@example.com',
                'passport_number' => 'IL998877',
            ])
            ->assertHasNoFormComponentActionErrors();

        $createdCustomer = Customer::where('name', 'דניאל לוי')->first();
        $this->assertNotNull($createdCustomer);
        $this->assertSame($tenant->id, $createdCustomer->tenant_id);
        $this->assertSame('0529876543', $createdCustomer->phone);
    }
}
