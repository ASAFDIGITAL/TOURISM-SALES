<?php

namespace Tests\Feature;

use App\Mail\TripMessageMail;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\ReminderRuleLog;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReminderRuleEngine;
use App\Services\TripMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessagingQuotasAndRuleLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_plans_have_100_300_500_emails_and_50_100_180_sms(): void
    {
        Plan::ensureDefaultPlansExist();

        $basic = Plan::where('slug', 'basic')->firstOrFail();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $business = Plan::where('slug', 'business')->firstOrFail();

        $this->assertSame(100, $basic->monthly_email_limit);
        $this->assertSame(50, $basic->monthly_sms_limit);

        $this->assertSame(300, $pro->monthly_email_limit);
        $this->assertSame(100, $pro->monthly_sms_limit);

        $this->assertSame(500, $business->monthly_email_limit);
        $this->assertSame(180, $business->monthly_sms_limit);
    }

    public function test_monthly_email_and_sms_quotas_are_enforced_logged_and_expandable_by_admin(): void
    {
        Mail::fake();
        Http::fake([
            '*' => Http::response(['StatusId' => 1, 'StatusDescription' => 'OK'], 200),
        ]);
        config()->set('services.inforu.basic_auth', 'dGVzdDp0b2tlbg==');

        $plan = Plan::create([
            'slug' => 'tiny-quota',
            'name_he' => 'בדיקה',
            'name_en' => 'Tiny',
            'monthly_price' => 99,
            'yearly_price' => 990,
            'currency' => 'ILS',
            'included_users' => 1,
            'extra_user_price' => 49,
            'trial_days' => 14,
            'reminders_enabled' => true,
            'allowed_channels' => ['push', 'email', 'whatsapp', 'sms'],
            'monthly_email_limit' => 1,
            'monthly_sms_limit' => 1,
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Quota Agency',
            'status' => 'active',
            'plan_id' => $plan->id,
            'reminders_enabled' => true,
            'allowed_channels' => ['push', 'email', 'whatsapp', 'sms'],
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Agency Boss',
            'email' => 'boss@quota.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dana Levi',
            'email' => 'dana@quota.test',
            'phone' => '0521234567',
        ]);

        $trip1 = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Trip One',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'total_amount' => 4000,
            'status' => 'confirmed',
        ]);

        $trip2 = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Trip Two',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'total_amount' => 5000,
            'status' => 'confirmed',
        ]);

        $rule = ReminderRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Pre-Trip Rule',
            'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_TRIP,
            'days_offset' => 5,
            'recipient_type' => 'customer',
            'channel' => 'email,sms',
            'template_type' => TripMessageService::TEMPLATE_PRE_TRIP_REMINDER,
            'message_language' => 'he',
            'is_active' => true,
        ]);

        // Process rules: Trip 1 should consume 1 email + 1 SMS; Trip 2 should be blocked by quota!
        $triggered = app(ReminderRuleEngine::class)->processAllRules();
        $this->assertSame(2, $triggered);

        Mail::assertSent(TripMessageMail::class, 1);
        $this->assertSame(1, $tenant->getMonthlyEmailSentCount());
        $this->assertSame(1, $tenant->getMonthlySmsSentCount());
        $this->assertSame(0, $tenant->getRemainingMonthlyEmails());
        $this->assertSame(0, $tenant->getRemainingMonthlySms());

        $logs = ReminderRuleLog::where('reminder_rule_id', $rule->id)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('sent', $logs[0]->status);
        $this->assertStringContainsString('email', (string) $logs[0]->channels_sent);
        $this->assertStringContainsString('sms', (string) $logs[0]->channels_sent);

        $this->assertSame('quota_exceeded', $logs[1]->status);
        $this->assertStringContainsString('email_quota_exceeded', (string) $logs[1]->note);
        $this->assertStringContainsString('sms_quota_exceeded', (string) $logs[1]->note);

        // Direct email send is also blocked while at limit
        $blockedSend = app(TripMessageService::class)->sendEmail($trip2, 'dana@quota.test', 'Sub', 'Body');
        $this->assertFalse($blockedSend);

        // Admin increases extra quota -> sending succeeds again immediately!
        $tenant->update([
            'extra_email_quota' => 5,
            'extra_sms_quota' => 5,
        ]);
        $tenant->refresh();

        $this->assertSame(6, $tenant->getMonthlyEmailLimit());
        $this->assertSame(5, $tenant->getRemainingMonthlyEmails());

        $sentAfterIncrease = app(TripMessageService::class)->sendEmail($trip2->fresh(), 'dana@quota.test', 'Sub', 'Body');
        $this->assertTrue($sentAfterIncrease);
        Mail::assertSent(TripMessageMail::class, 2);

        // Agent reminder-rules page renders usage widget & log columns cleanly
        $this->actingAs($owner)
            ->get('/agent/reminder-rules')
            ->assertOk()
            ->assertSee('Pre-Trip Rule')
            ->assertSee('2 / 6')
            ->assertSee('1 / 6');

        // Admin tenants page renders usage badges cleanly
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@quota.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $this->flushSession();

        $this->actingAs($admin)
            ->get('/admin/tenants')
            ->assertOk()
            ->assertSee('Quota Agency')
            ->assertSee('2 / 6')
            ->assertSee('1 / 6');
    }
}
