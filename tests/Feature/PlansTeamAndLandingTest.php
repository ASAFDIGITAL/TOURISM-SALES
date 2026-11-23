<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\SalesReports;
use App\Filament\Agent\Widgets\RevenueChartWidget;
use App\Filament\Agent\Widgets\UnpaidTripsWidget;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlansTeamAndLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_default_plans_with_12_percent_yearly_discount_and_whatsapp(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('TOURISM-SALES')
            ->assertSee('סטארט (Start)')
            ->assertSee('פרו (Pro)')
            ->assertSee('ביזנס (Business)')
            ->assertSee('050-231-1554')
            ->assertSee('972502311554')
            ->assertSee('12%');

        $this->assertSame(3, Plan::count());
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $this->assertEquals(299.0, (float) $pro->monthly_price);
        $this->assertEquals(3157.0, (float) $pro->yearly_price);
    }

    public function test_self_registration_creates_trial_tenant_owner_rules_and_notifies_admin(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'boss@asafdigital.com',
            'password' => Hash::make('secret123'),
            'role' => 'super_admin',
        ]);

        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->get('/register?plan=pro')
            ->assertOk()
            ->assertSee('פרו (Pro)');

        $response = $this->post('/register', [
            'agency_name' => 'נווה מדבר תיירות',
            'owner_name' => 'יוסי כהן',
            'email' => 'yossi@desert.test',
            'phone' => '0509998877',
            'password' => 'Pass1234!',
            'password_confirmation' => 'Pass1234!',
            'plan_id' => $pro->id,
            'currency' => 'ILS',
        ]);

        $response->assertRedirect('/agent');

        $tenant = Tenant::where('name', 'נווה מדבר תיירות')->first();
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->is_trial);
        $this->assertTrue($tenant->isOnTrial());
        $this->assertSame($pro->id, $tenant->plan_id);
        $this->assertSame(3, $tenant->getMaxUsers());

        $owner = User::where('email', 'yossi@desert.test')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->isAgencyOwner());
        $this->assertSame($tenant->id, $owner->tenant_id);

        // Admin received DB notification about the new trial signup
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_staff_permissions_hide_revenue_reports_settings_and_scope_trips(): void
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Team Agency',
            'status' => 'active',
            'plan_id' => $pro->id,
            'extra_seats' => 0,
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@team.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        $salesRep = User::create([
            'name' => 'Sales Rep',
            'email' => 'rep@team.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_STAFF,
            'permissions' => User::rolePresetPermissions('sales_rep'),
            'tenant_id' => $tenant->id,
        ]);

        $ownerCustomer = Customer::create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'name' => 'Owner Client',
        ]);

        $repCustomer = Customer::create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $salesRep->id,
            'name' => 'Rep Client',
        ]);

        $ownerTrip = Trip::create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'customer_id' => $ownerCustomer->id,
            'title' => 'Owner Secret Trip',
            'total_amount' => 10000,
            'status' => 'confirmed',
        ]);

        $repTrip = Trip::create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $salesRep->id,
            'customer_id' => $repCustomer->id,
            'title' => 'Rep Own Trip',
            'total_amount' => 3000,
            'status' => 'confirmed',
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'trip_id' => $repTrip->id,
            'amount' => 1500,
            'method' => 'credit',
            'paid_at' => now(),
        ]);

        // Sales rep blocked from financial/admin pages
        $this->actingAs($salesRep)->get('/agent/sales-reports')->assertForbidden();
        $this->actingAs($salesRep)->get('/agent/receipts')->assertForbidden();
        $this->actingAs($salesRep)->get('/agent/agent-settings')->assertForbidden();
        $this->actingAs($salesRep)->get('/agent/staff')->assertForbidden();

        // Sales rep dashboard hides financial charts & unpaid widgets
        $this->actingAs($salesRep)
            ->get('/agent')
            ->assertOk()
            ->assertDontSeeLivewire(RevenueChartWidget::class)
            ->assertDontSeeLivewire(UnpaidTripsWidget::class);

        // Sales rep only sees their own trips, NOT owner's trips
        $this->actingAs($salesRep)
            ->get('/agent/trips')
            ->assertOk()
            ->assertSee('Rep Own Trip')
            ->assertDontSee('Owner Secret Trip');

        // Seat quota enforcement: Pro has 3 seats (owner + rep = 2 -> can add 1 more)
        $this->assertTrue($tenant->canAddMoreUsers());
        User::create([
            'name' => 'Third Seat',
            'email' => 'third@team.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_STAFF,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertFalse($tenant->canAddMoreUsers());

        // Adding paid extra seat unlocks capacity immediately
        $tenant->update(['extra_seats' => 1]);
        $this->assertTrue($tenant->fresh()->canAddMoreUsers());

        // Owner sees employee performance report for both owner & salesRep
        $this->actingAs($owner);
        $salesPage = new SalesReports();
        $salesPage->mount();
        $perf = $salesPage->getEmployeePerformance();
        $this->assertCount(2, $perf);
        $this->assertSame('Owner User', $perf[0]['name']);
        $this->assertSame('Sales Rep', $perf[1]['name']);
    }

    public function test_expired_trial_tenant_is_blocked_from_agent_panel(): void
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $pro->update(['upay_monthly_url' => 'https://app.upay.co.il/API6/s.php?m=PAYWALLPRO']);

        $tenant = Tenant::create([
            'name' => 'Expired Trial',
            'email' => 'expired@trial.test',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $owner = User::create([
            'name' => 'Trial Owner',
            'email' => 'expired@trial.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        // Visiting /agent or /agent/trips redirects to billing paywall
        $this->actingAs($owner)
            ->get('/agent')
            ->assertRedirect('/agent/billing-and-invoices');

        $this->actingAs($owner)
            ->get('/agent/trips')
            ->assertRedirect('/agent/billing-and-invoices');

        // Billing page renders the non-dismissible paywall modal with payment link
        $this->actingAs($owner)
            ->get('/agent/billing-and-invoices')
            ->assertOk()
            ->assertSee('tsTrialPaywallModal')
            ->assertSee('תקופת הניסיון החינמית שלך הסתיימה')
            ->assertSee('https://app.upay.co.il/API6/s.php?m=PAYWALLPRO');

        // Once trial is cleared after payment, paywall disappears and /agent unlocks
        $tenant->update(['is_trial' => false, 'trial_ends_at' => null, 'expires_at' => now()->addMonth()->toDateString()]);

        $this->actingAs($owner->fresh())
            ->get('/agent')
            ->assertOk()
            ->assertDontSee('tsTrialPaywallModal');

        // Expired paid recurring subscription ALSO triggers the non-dismissible renewal paywall
        $tenant->update(['expires_at' => now()->subDays(5)->toDateString()]);

        $this->actingAs($owner->fresh())
            ->get('/agent/trips')
            ->assertRedirect('/agent/billing-and-invoices');

        $this->actingAs($owner->fresh())
            ->get('/agent/billing-and-invoices')
            ->assertOk()
            ->assertSee('tsTrialPaywallModal')
            ->assertSee('תוקף המנוי שלך הסתיים');

        // Lifetime subscription is NEVER locked even if expires_at is in the past
        $tenant->update(['subscription_type' => 'lifetime']);

        $this->actingAs($owner->fresh())
            ->get('/agent')
            ->assertOk()
            ->assertDontSee('tsTrialPaywallModal');
    }

    public function test_legal_pages_accessibility_and_cookies_work_in_all_languages(): void
    {
        foreach (['he' => 'מדיניות פרטיות', 'ar' => 'سياسة الخصوصية', 'en' => 'Privacy & Cookies Policy'] as $locale => $expectedPrivacy) {
            $this->withSession(['locale' => $locale])
                ->get('/privacy-policy')
                ->assertOk()
                ->assertSee($expectedPrivacy)
                ->assertSee('tsA11yPanel')
                ->assertSee('tsCookieBanner');

            $this->withSession(['locale' => $locale])
                ->get('/accessibility')
                ->assertOk()
                ->assertSee('050-231-1554');

            $this->withSession(['locale' => $locale])
                ->get('/terms')
                ->assertOk();
        }
    }
}
