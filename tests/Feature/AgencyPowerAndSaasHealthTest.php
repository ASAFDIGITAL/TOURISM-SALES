<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\SalesReports;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgencyPowerAndSaasHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_cost_and_net_profit_in_trips_and_sales_reports(): void
    {
        $tenant = Tenant::create([
            'name' => 'Profit Agency',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Profit Owner',
            'email' => 'owner@profit.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Avi Client',
        ]);

        $trip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'customer_id' => $customer->id,
            'title' => 'Dubai Luxury',
            'total_amount' => 10000,
            'supplier_name' => 'FlyDubai Wholesale',
            'supplier_cost' => 7200,
            'status' => Trip::STATUS_CONFIRMED,
        ]);

        $this->assertEquals(2800.00, $trip->net_profit);
        $this->assertEquals(28.0, $trip->profit_margin);

        $this->actingAs($owner);
        $report = new SalesReports();
        $report->mount();
        $stats = $report->getStats();

        $this->assertSame('₪2,800.00', $stats['net_profit']);
        $this->assertSame('₪7,200.00', $stats['supplier_cost']);
        $this->assertEquals(28.0, $stats['margin']);

        $empPerf = $report->getEmployeePerformance();
        $this->assertCount(1, $empPerf);
        $this->assertSame('₪2,800.00', $empPerf[0]['profit']);
    }

    public function test_six_month_passport_expiry_alert_command_notifies_agent_once(): void
    {
        $tenant = Tenant::create([
            'name' => 'Passport Agency',
            'status' => 'active',
            'reminders_enabled' => true,
            'allowed_channels' => ['push', 'email'],
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Passport Owner',
            'email' => 'owner@passport.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        // Passport expires in 3 months (less than 6 months after departure in 15 days!)
        $riskCustomer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Short Passport Guy',
            'passport_number' => 'IL998877',
            'passport_expires_at' => now()->addMonths(3)->toDateString(),
        ]);

        // Passport valid for 3 years (safe!)
        $safeCustomer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Safe Passport Gal',
            'passport_number' => 'IL112233',
            'passport_expires_at' => now()->addYears(3)->toDateString(),
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'customer_id' => $riskCustomer->id,
            'title' => 'Paris Next Month',
            'start_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 4500,
            'status' => Trip::STATUS_CONFIRMED,
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'customer_id' => $safeCustomer->id,
            'title' => 'London Next Month',
            'start_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 4500,
            'status' => Trip::STATUS_CONFIRMED,
        ]);

        $this->artisan('app:check-passport-expiries')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());

        // Second run in the same week is deduplicated
        $this->artisan('app:check-passport-expiries')->assertSuccessful();
        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_admin_saas_health_mrr_and_inactive_tenants_widget_and_activity_tracking(): void
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail(); // 299/mo

        $paidActive = Tenant::create([
            'name' => 'Active Paid Co',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => false,
            'expires_at' => now()->addMonth()->toDateString(),
            'last_activity_at' => now()->subDays(1),
        ]);

        $inactiveTenant = Tenant::create([
            'name' => 'Sleeping Agency',
            'phone' => '0504445566',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(5),
            'last_activity_at' => now()->subDays(10),
        ]);

        // Visiting /agent updates last_activity_at via TrackTenantActivity middleware
        $owner = User::create([
            'name' => 'Paid Owner',
            'email' => 'paid@health.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $paidActive->id,
        ]);

        $this->actingAs($owner)->get('/agent')->assertOk();
        $this->assertNotNull($owner->fresh()->last_login_at);

        // Admin dashboard shows MRR (₪299) and Sleeping Agency in inactive widget
        $admin = User::create([
            'name' => 'Super Boss',
            'email' => 'boss@health.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $this->flushSession();
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('₪299')
            ->assertSee('Sleeping Agency');
    }
}
