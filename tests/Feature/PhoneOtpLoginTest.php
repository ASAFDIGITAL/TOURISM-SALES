<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.inforu.endpoint', 'https://capi.inforu.co.il/api/v2/SMS/SendSms');
        config()->set('services.inforu.basic_auth', 'dGVzdDp0b2tlbg==');
        config()->set('services.inforu.sender', 'AsafDigital');
    }

    public function test_user_can_login_with_phone_and_inforu_sms_otp(): void
    {
        $capturedOtp = null;

        Http::fake(function (Request $request) use (&$capturedOtp) {
            $body = $request->data();
            preg_match('/(\d{6})/', $body['Data']['Message'] ?? '', $matches);
            $capturedOtp = $matches[1] ?? null;

            return Http::response([
                'StatusId' => 1,
                'StatusDescription' => 'Success',
            ], 200);
        });

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'phone' => '050-123-4567',
        ]);

        $component = Livewire::test(Login::class)
            ->call('switchLoginMethod', 'phone')
            ->set('phone', '+972-50-123-4567')
            ->call('sendOtp')
            ->assertHasNoErrors()
            ->assertSet('otpSent', true);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://capi.inforu.co.il/api/v2/SMS/SendSms'
                && $request->hasHeader('Authorization', 'Basic dGVzdDp0b2tlbg==')
                && ($request->data()['Data']['Recipients'][0]['Phone'] ?? null) === '0501234567'
                && ($request->data()['Data']['Settings']['Sender'] ?? null) === 'AsafDigital';
        });

        $this->assertNotNull($capturedOtp);

        $component
            ->set('otpCode', $capturedOtp)
            ->call('verifyOtp')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_agent_can_login_using_single_agent_tenant_phone_fallback(): void
    {
        $capturedOtp = null;

        Http::fake(function (Request $request) use (&$capturedOtp) {
            preg_match('/(\d{6})/', $request->data()['Data']['Message'] ?? '', $matches);
            $capturedOtp = $matches[1] ?? null;

            return Http::response(['StatusId' => 1], 200);
        });

        $tenant = Tenant::create([
            'name' => 'Agency One',
            'phone' => '054-7778899',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $tenant->id,
            'phone' => null,
        ]);

        Livewire::test(Login::class)
            ->call('switchLoginMethod', 'phone')
            ->set('phone', '0547778899')
            ->call('sendOtp')
            ->assertSet('otpSent', true)
            ->set('otpCode', $capturedOtp)
            ->call('verifyOtp')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($agent);
    }

    public function test_invalid_otp_is_rejected_and_unknown_phone_fails(): void
    {
        Http::fake([
            'capi.inforu.co.il/*' => Http::response(['StatusId' => 1], 200),
        ]);

        User::factory()->create([
            'role' => 'super_admin',
            'phone' => '0509998888',
        ]);

        // Unknown phone
        Livewire::test(Login::class)
            ->call('switchLoginMethod', 'phone')
            ->set('phone', '0500000000')
            ->call('sendOtp')
            ->assertHasErrors(['phone']);

        // Wrong OTP
        Livewire::test(Login::class)
            ->call('switchLoginMethod', 'phone')
            ->set('phone', '0509998888')
            ->call('sendOtp')
            ->assertSet('otpSent', true)
            ->set('otpCode', '999999')
            ->call('verifyOtp')
            ->assertHasErrors(['otpCode']);

        $this->assertGuest();
    }

    public function test_suspended_agent_cannot_request_otp(): void
    {
        Http::fake();

        $tenant = Tenant::create([
            'name' => 'Suspended Agency',
            'status' => 'suspended',
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $tenant->id,
            'phone' => '0531112222',
        ]);

        Livewire::test(Login::class)
            ->call('switchLoginMethod', 'phone')
            ->set('phone', '0531112222')
            ->call('sendOtp')
            ->assertHasErrors(['phone'])
            ->assertSet('otpSent', false);

        Http::assertNothingSent();
    }
}
