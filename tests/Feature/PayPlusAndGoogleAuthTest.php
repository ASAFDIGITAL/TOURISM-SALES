<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayPlusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPlusAndGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_payplus_recurring_link_vat_calculation_and_signed_webhook_flow(): void
    {
        config()->set('payplus.env', 'sandbox');
        config()->set('payplus.api_key', 'test-api-key');
        config()->set('payplus.secret_key', 'test-secret-key');
        config()->set('payplus.payment_page_uid', 'page-uid-123');
        config()->set('payplus.vat_rate', 18);

        $admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin@payplus.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Sun Travel',
            'email' => 'sun@travel.test',
            'phone' => '0501234567',
            'status' => 'active',
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(10),
            'plan_id' => $pro->id,
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Sun Owner',
            'email' => 'owner@sun.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        // 1. VAT calculation (Pro 299 + 1 extra seat 49 = 348 net; +18% VAT = 410.64)
        $payPlus = app(PayPlusService::class);
        $totals = $payPlus->calculateTotals($pro, 1, 'monthly');
        $this->assertEquals(348.00, $totals['subtotal']);
        $this->assertEquals(62.64, $totals['vat_amount']);
        $this->assertEquals(410.64, $totals['total']);

        // 2. Recurring payment link generation via PayPlus API
        Http::fake([
            'restapidev.payplus.co.il/api/v1.0/PaymentPages/generateLink' => Http::response([
                'results' => ['status' => 'success', 'code' => 0],
                'data' => [
                    'page_request_uid' => 'req-uid-999',
                    'payment_page_link' => 'https://payments.payplus.co.il/checkout/req-uid-999',
                ],
            ], 200),
        ]);

        $result = $payPlus->createRecurringPaymentLink($tenant, $pro, 'monthly', 1);
        $this->assertTrue($result['ok']);
        $this->assertSame('https://payments.payplus.co.il/checkout/req-uid-999', $result['payment_url']);
        $invoice = $result['invoice'];
        $this->assertSame(SubscriptionInvoice::STATUS_PENDING, $invoice->status);

        // 3. Reject unsigned/invalid webhook
        $this->postJson('/webhooks/payplus', ['test' => true], [
            'User-Agent' => 'PayPlus',
            'hash' => 'invalid-signature',
        ])->assertUnauthorized();

        // 4. Process valid HMAC-SHA256 signed PayPlus initial webhook with Invoice+ URL
        $webhookPayload = [
            'transaction' => [
                'uid' => 'tx-initial-001',
                'payment_page_request_uid' => 'req-uid-999',
                'status_code' => '000',
                'amount' => 410.64,
                'currency' => 'ILS',
                'more_info' => (string) $tenant->id,
                'more_info_2' => (string) $pro->id,
                'more_info_3' => 'monthly',
                'more_info_4' => '1',
                'more_info_5' => (string) $invoice->id,
                'recurring_charge_information' => [
                    'recurring_uid' => 'rec-uid-555',
                ],
            ],
            'data' => [
                'customer_uid' => 'cust-uid-777',
                'card_information' => ['four_digits' => '4242'],
            ],
            'invoice' => [
                'number' => 'INV-2026-001',
                'original_doc_url' => 'https://invoices.payplus.co.il/doc/INV-2026-001.pdf',
            ],
        ];

        $raw = json_encode($webhookPayload);
        $sig = base64_encode(hash_hmac('sha256', $raw, 'test-secret-key', true));

        $this->call('POST', '/webhooks/payplus', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'PayPlus',
            'HTTP_HASH' => $sig,
        ], $raw)->assertOk();

        $invoice->refresh();
        $tenant->refresh();
        $this->assertSame(SubscriptionInvoice::STATUS_PAID, $invoice->status);
        $this->assertSame('INV-2026-001', $invoice->payplus_invoice_number);
        $this->assertSame('https://invoices.payplus.co.il/doc/INV-2026-001.pdf', $invoice->payplus_invoice_url);
        $this->assertFalse($tenant->is_trial);
        $this->assertTrue($tenant->hasActivePayPlusStandingOrder());
        $this->assertSame('4242', $tenant->payplus_card_last4);
        $this->assertSame(1, $tenant->extra_seats);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $owner->notifications()->count());

        // 5. Subsequent automatic recurring renewal webhook creates a 2nd paid invoice!
        $renewalPayload = [
            'transaction' => [
                'uid' => 'tx-renewal-002',
                'status_code' => '000',
                'amount' => 410.64,
                'currency' => 'ILS',
                'recurring_charge_information' => [
                    'recurring_uid' => 'rec-uid-555',
                ],
            ],
            'invoice' => [
                'number' => 'INV-2026-002',
                'original_doc_url' => 'https://invoices.payplus.co.il/doc/INV-2026-002.pdf',
            ],
        ];

        $raw2 = json_encode($renewalPayload);
        $sig2 = base64_encode(hash_hmac('sha256', $raw2, 'test-secret-key', true));

        $this->call('POST', '/webhooks/payplus', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'PayPlus',
            'HTTP_HASH' => $sig2,
        ], $raw2)->assertOk();

        $this->assertSame(2, SubscriptionInvoice::where('tenant_id', $tenant->id)->where('status', 'paid')->count());

        // 6. Owner can view Billing & Invoices page
        $this->actingAs($owner)
            ->get('/agent/billing-and-invoices')
            ->assertOk()
            ->assertSee('INV-2026-001')
            ->assertSee('INV-2026-002');
    }

    public function test_google_oauth_registers_new_trial_agency_and_logs_in_existing_user(): void
    {
        config()->set('services.google.client_id', 'google-client-id');
        config()->set('services.google.client_secret', 'google-client-secret');

        $admin = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin@google.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-google-token'], 200),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-12345',
                'email' => 'dana.google@agency.test',
                'name' => 'Dana Google',
            ], 200),
        ]);

        // Redirect puts CSRF state in session
        $redirectResponse = $this->get('/auth/google?plan=business');
        $redirectResponse->assertRedirect();
        $state = session('google_oauth_state');
        $this->assertNotEmpty($state);

        // Callback creates trial tenant + owner + logs in
        $this->get('/auth/google/callback?state=' . $state . '&code=test-code')
            ->assertRedirect('/agent');

        $user = User::where('email', 'dana.google@agency.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('google-sub-12345', $user->google_id);
        $this->assertTrue($user->isAgencyOwner());
        $this->assertTrue($user->tenant->is_trial);
        $this->assertSame('business', $user->tenant->plan->slug);
        $this->assertSame(1, $admin->notifications()->count());

        // Logging in again via Google finds the existing user without duplicating tenant
        auth()->logout();
        $this->get('/auth/google');
        $state2 = session('google_oauth_state');
        $this->get('/auth/google/callback?state=' . $state2 . '&code=test-code-2')
            ->assertRedirect('/agent');

        $this->assertSame(1, Tenant::where('email', 'dana.google@agency.test')->count());
    }
}
