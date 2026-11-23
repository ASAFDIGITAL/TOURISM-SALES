<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\AgentSettings;
use App\Filament\Agent\Pages\SalesReports;
use App\Filament\Agent\Widgets\AgentStatsOverview;
use App\Filament\Agent\Widgets\MonthlyTripsWidget;
use App\Filament\Agent\Widgets\UnpaidTripsWidget;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTenantImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'tenant_id' => null,
            'language' => 'he',
        ]);
    }

    private function createTenant(string $name, string $status = 'active'): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'status' => $status,
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);
    }

    public function test_super_admin_cannot_open_agent_panel_without_impersonating(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)
            ->get('/agent')
            ->assertRedirect('/admin');
    }

    public function test_super_admin_can_enter_tenant_panel_view_scoped_data_and_create_records(): void
    {
        $admin = $this->createSuperAdmin();
        $tenantA = $this->createTenant('Agency Alpha');
        $tenantB = $this->createTenant('Agency Beta');

        $customerA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Alpha Customer',
        ]);
        $customerB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Beta Customer',
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'title' => 'Alpha Paris Trip',
            'destination' => 'Paris',
            'total_amount' => 1000,
            'status' => 'confirmed',
        ]);
        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'title' => 'Beta Rome Trip',
            'destination' => 'Rome',
            'total_amount' => 2000,
            'status' => 'confirmed',
        ]);

        // Enter tenant A
        $this->actingAs($admin)
            ->post(route('admin.impersonate.enter', $tenantA))
            ->assertRedirect('/agent')
            ->assertSessionHas(User::IMPERSONATED_TENANT_SESSION_KEY, $tenantA->id);

        // Agent panel dashboard loads with impersonation banner
        $response = $this->actingAs($admin)->get('/agent');
        $response->assertOk();
        $response->assertSee('Agency Alpha');
        $response->assertDontSee('Beta Customer');

        // Agent widgets render scoped to tenant A
        Livewire::actingAs($admin)
            ->test(MonthlyTripsWidget::class)
            ->assertSee('Alpha Customer')
            ->assertDontSee('Beta Customer');

        Livewire::actingAs($admin)
            ->test(UnpaidTripsWidget::class)
            ->assertSee('Alpha Paris Trip')
            ->assertDontSee('Beta Rome Trip');

        Livewire::actingAs($admin)
            ->test(AgentStatsOverview::class)
            ->assertOk();

        // Agent resources (customers, trips, sales reports, settings) load cleanly
        $this->actingAs($admin)->get('/agent/customers')
            ->assertOk()
            ->assertSee('Alpha Customer')
            ->assertDontSee('Beta Customer');

        $this->actingAs($admin)->get('/agent/trips')
            ->assertOk()
            ->assertSee('Alpha Paris Trip')
            ->assertDontSee('Beta Rome Trip');

        $this->actingAs($admin)->get('/agent/sales-reports')->assertOk();
        $this->actingAs($admin)->get('/agent/agent-settings')->assertOk();

        Livewire::actingAs($admin)
            ->test(SalesReports::class)
            ->assertSee('Alpha Customer')
            ->assertDontSee('Beta Customer');

        Livewire::actingAs($admin)
            ->test(AgentSettings::class)
            ->assertSet('data.name', 'Agency Alpha');

        // Creating a model while impersonating automatically assigns the impersonated tenant_id
        $created = Customer::create(['name' => 'Created By Admin']);
        $this->assertEquals($tenantA->id, $created->tenant_id);

        // Switching to tenant B updates scope to tenant B
        $this->actingAs($admin)
            ->post(route('admin.impersonate.switch'), ['tenant_id' => $tenantB->id])
            ->assertRedirect('/agent')
            ->assertSessionHas(User::IMPERSONATED_TENANT_SESSION_KEY, $tenantB->id);

        $this->actingAs($admin)->get('/agent/customers')
            ->assertOk()
            ->assertSee('Beta Customer')
            ->assertDontSee('Alpha Customer');

        // Leaving impersonation returns to admin panel and blocks /agent again
        $this->actingAs($admin)
            ->post(route('admin.impersonate.leave'))
            ->assertRedirect('/admin')
            ->assertSessionMissing(User::IMPERSONATED_TENANT_SESSION_KEY);

        $this->actingAs($admin)
            ->get('/agent')
            ->assertRedirect('/admin');
    }

    public function test_super_admin_can_enter_suspended_tenant_without_users(): void
    {
        $admin = $this->createSuperAdmin();
        $suspendedTenant = $this->createTenant('Suspended Agency', 'suspended');

        $this->actingAs($admin)
            ->post(route('admin.impersonate.enter', $suspendedTenant))
            ->assertRedirect('/agent');

        $this->actingAs($admin)
            ->get('/agent')
            ->assertOk()
            ->assertSee('Suspended Agency');
    }

    public function test_agent_cannot_use_impersonation_endpoints(): void
    {
        $tenantA = $this->createTenant('Agency Alpha');
        $tenantB = $this->createTenant('Agency Beta');
        $agent = User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $tenantA->id,
        ]);

        $this->actingAs($agent)
            ->post(route('admin.impersonate.enter', $tenantB))
            ->assertForbidden();

        $this->actingAs($agent)
            ->post(route('admin.impersonate.switch'), ['tenant_id' => $tenantB->id])
            ->assertForbidden();

        $this->actingAs($agent)
            ->post(route('admin.impersonate.leave'))
            ->assertForbidden();
    }
}
