<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UPayBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_upay_plan_link_renders_and_webhook_activates_subscription_and_saves_invoice(): void
    {
        Plan::ensureDefaultPlansExist();

        $startPlan = Plan::where('slug', 'basic')->firstOrFail();
        $startPlan->update([
            'upay_monthly_url' => 'https://app.upay.co.il/API6/s.php?m=TESTSTART123',
        ]);

        $admin = User::create([
            'name' => 'Boss',
            'email' => 'admin@upay.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $tenant = Tenant::create([
            'name' => 'Sunny Travel',
            'email' => 'sunny@travel.test',
            'phone' => '0501112233',
            'status' => 'active',
            'plan_id' => $startPlan->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(10),
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Sunny Owner',
            'email' => 'sunny@travel.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        // 1. Agent billing page shows the direct UPay standing order button
        $this->actingAs($owner)
            ->get('/agent/billing-and-invoices')
            ->assertOk()
            ->assertSee('https://app.upay.co.il/API6/s.php?m=TESTSTART123');

        // 2. UPay Server Update Webhook activates subscription, clears trial & saves invoice
        $response = $this->post('/webhooks/upay', [
            'email' => 'sunny@travel.test',
            'amount' => '175.82',
            'transactionid' => 'UPAY-TX-9988',
            'standingorderid' => 'SO-7766',
            'invoicenumber' => 'INV-5544',
            'invoiceurl' => 'https://app.upay.co.il/docs/INV-5544.pdf',
            'last4' => '9876',
            'status' => 'success',
            'productname' => 'סטארט (Start)',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $tenant->refresh();
        $this->assertFalse($tenant->is_trial);
        $this->assertSame('active', $tenant->payplus_status);
        $this->assertSame('9876', $tenant->payplus_card_last4);
        $this->assertSame($startPlan->id, $tenant->plan_id);

        $invoice = SubscriptionInvoice::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('upay', $invoice->provider);
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(175.82, (float) $invoice->total_amount);
        $this->assertEquals(149.00, (float) $invoice->subtotal_amount);
        $this->assertSame('INV-5544', $invoice->payplus_invoice_number);
        $this->assertSame('https://app.upay.co.il/docs/INV-5544.pdf', $invoice->payplus_invoice_url);

        // Admin & owner both notified
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $owner->notifications()->count());

        // 3. Duplicate webhook with same transactionid is ignored cleanly
        $this->post('/webhooks/upay', [
            'email' => 'sunny@travel.test',
            'amount' => '175.82',
            'transactionid' => 'UPAY-TX-9988',
            'status' => 'success',
        ])->assertOk();

        $this->assertSame(1, SubscriptionInvoice::where('tenant_id', $tenant->id)->count());

        // 4. Return URL redirects cleanly to billing page
        $this->actingAs($owner)
            ->get('/billing/upay/return?status=success')
            ->assertRedirect('/agent/billing-and-invoices');
    }
}
