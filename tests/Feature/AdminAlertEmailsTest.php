<?php

namespace Tests\Feature;

use App\Mail\AdminAlertMail;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SupportTicketService;
use App\Services\UPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAlertEmailsTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::create([
            'name' => 'Asaf Admin',
            'email' => 'admin@asafdigital.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'language' => 'he',
        ]);
    }

    public function test_admin_receives_email_on_new_agency_registration(): void
    {
        Mail::fake();
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $admin = $this->createSuperAdmin();

        $response = $this->post('/register', [
            'agency_name' => 'Golden Horizon Tours',
            'owner_name' => 'Yossi Cohen',
            'email' => 'yossi@goldenhorizon.test',
            'phone' => '050-9876543',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'plan_id' => $pro->id,
            'currency' => 'ILS',
        ]);

        $response->assertRedirect('/agent');

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) use ($admin): bool {
            $html = $mail->render();

            return $mail->hasTo($admin->email)
                && str_contains($html, 'Golden Horizon Tours')
                && str_contains($html, 'Yossi Cohen')
                && str_contains($html, '050-9876543')
                && str_contains($html, '/admin/tenants/');
        });
    }

    public function test_admin_receives_email_on_new_support_ticket_and_upay_payment(): void
    {
        Mail::fake();
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $admin = $this->createSuperAdmin();

        $tenant = Tenant::create([
            'name' => 'Blue Sky Travel',
            'email' => 'billing@bluesky.test',
            'phone' => '052-5556677',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(10),
            'language' => 'he',
            'currency' => 'ILS',
        ]);

        $agent = User::create([
            'name' => 'Dana Levy',
            'email' => 'billing@bluesky.test',
            'phone' => '052-5556677',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
            'language' => 'he',
        ]);

        // 1. New support ticket triggers admin email
        app(SupportTicketService::class)->createTicket(
            tenant: $tenant,
            creator: $agent,
            subject: 'עזרה בחיבור דומיין',
            body: 'שלום, נשמח לעזרה בהגדרות המערכת.',
        );

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) use ($admin): bool {
            $html = $mail->render();

            return $mail->hasTo($admin->email)
                && str_contains($html, 'Blue Sky Travel')
                && str_contains($html, 'עזרה בחיבור דומיין');
        });

        // 2. UPay subscription payment webhook triggers admin email
        $res = app(UPayService::class)->handleWebhookPayload([
            'email' => 'billing@bluesky.test',
            'amount' => '352.82',
            'status' => 'approved',
            'transactionid' => 'UPAY-ALERT-991',
            'invoicenumber' => 'INV-991',
        ]);
        $this->assertTrue($res['ok']);

        Mail::assertSent(AdminAlertMail::class, function (AdminAlertMail $mail) use ($admin): bool {
            $html = $mail->render();

            return $mail->hasTo($admin->email)
                && str_contains($html, 'Blue Sky Travel')
                && str_contains($html, 'INV-991')
                && str_contains($html, '352.82');
        });
    }
}
