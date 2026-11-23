<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\BillingAndInvoices;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayPlusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CouponsTest extends TestCase
{
    use RefreshDatabase;

    public function test_percentage_and_fixed_coupons_calculate_discounts_and_respect_limits(): void
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail(); // 299 ILS monthly

        $percentCoupon = Coupon::create([
            'code' => 'welcome20',
            'name' => '20% Launch',
            'discount_type' => Coupon::TYPE_PERCENT,
            'discount_value' => 20,
            'is_active' => true,
        ]);

        // Auto-uppercased
        $this->assertSame('WELCOME20', $percentCoupon->code);

        $payPlus = app(PayPlusService::class);
        $totals = $payPlus->calculateTotals($pro, 0, 'monthly', $percentCoupon);

        $this->assertTrue($totals['coupon_applied']);
        $this->assertEquals(299.00, $totals['original_subtotal']);
        $this->assertEquals(59.80, $totals['discount']);
        $this->assertEquals(239.20, $totals['subtotal']);
        $this->assertEquals(43.06, $totals['vat_amount']);
        $this->assertEquals(282.26, $totals['total']);

        // Fixed coupon restricted to basic plan does NOT apply to Pro
        $basic = Plan::where('slug', 'basic')->firstOrFail();
        $fixedBasic = Coupon::create([
            'code' => 'START50',
            'discount_type' => Coupon::TYPE_FIXED,
            'discount_value' => 50,
            'plan_id' => $basic->id,
            'is_active' => true,
        ]);

        $this->assertFalse($fixedBasic->isValidFor($pro, 'monthly'));
        $this->assertTrue($fixedBasic->isValidFor($basic, 'monthly'));

        $basicTotals = $payPlus->calculateTotals($basic, 0, 'monthly', $fixedBasic);
        $this->assertEquals(99.00, $basicTotals['subtotal']); // 149 - 50

        // Expired coupon is rejected
        $expired = Coupon::create([
            'code' => 'OLD10',
            'discount_type' => Coupon::TYPE_PERCENT,
            'discount_value' => 10,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $this->assertNull(Coupon::findValidByCode('OLD10'));
    }

    public function test_agent_can_apply_coupon_on_billing_page_and_admin_can_manage_coupons(): void
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $coupon = Coupon::create([
            'code' => 'VIP25',
            'name' => 'VIP Discount',
            'discount_type' => Coupon::TYPE_PERCENT,
            'discount_value' => 25,
            'upay_override_url' => 'https://app.upay.co.il/API6/s.php?m=VIP25LINK',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Coupon Agency',
            'email' => 'coupon@agency.test',
            'status' => 'active',
            'plan_id' => $pro->id,
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Agency Owner',
            'email' => 'coupon@agency.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        // Apply valid coupon via Livewire on BillingAndInvoices page
        $this->actingAs($owner);
        Livewire::test(BillingAndInvoices::class)
            ->set('couponCodeInput', 'vip25')
            ->call('applyCoupon')
            ->assertSet('appliedCouponCode', 'VIP25')
            ->assertSet('couponError', null)
            ->assertSee('VIP25')
            ->assertSee('https://app.upay.co.il/API6/s.php?m=VIP25LINK');

        $this->assertSame($coupon->id, $tenant->fresh()->coupon_id);

        // Invalid coupon sets error
        Livewire::test(BillingAndInvoices::class)
            ->set('couponCodeInput', 'FAKE999')
            ->call('applyCoupon')
            ->assertSet('appliedCouponCode', null)
            ->assertNotSet('couponError', null);

        // Admin coupons page renders cleanly
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@coupon.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $this->flushSession();
        $this->actingAs($admin)
            ->get('/admin/coupons')
            ->assertOk()
            ->assertSee('VIP25')
            ->assertSee('25%');
    }
}
