<?php

namespace Tests\Feature;

use App\Filament\Actions\SendTenantRenewalAction;
use App\Mail\TenantRenewalMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class LifetimeAndAdminRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiring_tenants_command_notifies_admin_for_recurring_only_and_skips_lifetime(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'language' => 'he',
            'notify_expiring_tenants' => true,
        ]);

        // Expiring recurring tenant (15 days left) -> MUST alert admin
        Tenant::create([
            'name' => 'Expiring Agency',
            'email' => 'exp@example.com',
            'status' => 'active',
            'subscription_type' => 'recurring',
            'expires_at' => now()->addDays(15)->format('Y-m-d'),
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        // Lifetime tenant (even if expires_at were set) -> MUST NOT alert admin
        Tenant::create([
            'name' => 'Lifetime Agency',
            'email' => 'life@example.com',
            'status' => 'active',
            'subscription_type' => 'lifetime',
            'expires_at' => now()->addDays(5)->format('Y-m-d'),
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        $mockPush = Mockery::mock(WebPushService::class);
        $mockPush->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function (User $u, string $title, string $body) use ($admin) {
                return $u->id === $admin->id && str_contains($title, 'Expiring Agency');
            })
            ->andReturn(1);
        $this->app->instance(WebPushService::class, $mockPush);

        $this->artisan('app:check-expiring-tenants')->assertSuccessful();
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_admin_mobile_notifications_page_and_renewal_email_with_payment_link_work(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'language' => 'he',
        ]);

        $this->actingAs($admin)
            ->get('/admin-mobile-notifications')
            ->assertOk()
            ->assertSee('manifest.webmanifest', false);

        $tenant = Tenant::create([
            'name' => 'Renew Agency',
            'email' => 'renew@example.com',
            'phone' => '050-9991111',
            'status' => 'active',
            'subscription_type' => 'recurring',
            'expires_at' => now()->addDays(10)->format('Y-m-d'),
            'subscription_amount' => 1200,
            'renewal_payment_url' => 'https://pay.example.com/renew-123',
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        $built = SendTenantRenewalAction::buildRenewalMessage($tenant, $tenant->renewal_payment_url, '1200', 'he');
        $this->assertStringContainsString('https://pay.example.com/renew-123', $built['body']);
        $this->assertStringContainsString('1,200.00', $built['body']);

        Mail::to($tenant->email)->send(new TenantRenewalMail(
            tenant: $tenant,
            subjectLine: $built['subject'],
            bodyText: $built['body'],
            paymentUrl: $tenant->renewal_payment_url,
            localeCode: 'he',
        ));

        Mail::assertSent(TenantRenewalMail::class, function (TenantRenewalMail $mail) {
            return $mail->hasTo('renew@example.com')
                && $mail->paymentUrl === 'https://pay.example.com/renew-123';
        });
    }
}
