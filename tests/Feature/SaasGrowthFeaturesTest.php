<?php

namespace Tests\Feature;

use App\Mail\TrialOnboardingMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\TrialOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SaasGrowthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_onboarding_drip_sends_milestones_without_duplicates(): void
    {
        Mail::fake();
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        // Day 0 tenant
        $day0 = Tenant::create([
            'name' => 'Day Zero Agency',
            'email' => 'day0@trial.test',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'language' => 'he',
        ]);

        // Day 11 tenant (3 days left)
        $day11 = Tenant::create([
            'name' => 'Day Eleven Agency',
            'email' => 'day11@trial.test',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
            'language' => 'he',
        ]);

        // Day 13 tenant (1 day left)
        $day13 = Tenant::create([
            'name' => 'Day Thirteen Agency',
            'email' => 'day13@trial.test',
            'status' => 'active',
            'plan_id' => $pro->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDay(),
            'language' => 'en',
        ]);

        $sentFirstRun = app(TrialOnboardingService::class)->processAllTrialTenants();
        $this->assertSame(3, $sentFirstRun);
        Mail::assertSent(TrialOnboardingMail::class, 3);

        // Running again on the same day sends ZERO duplicates!
        $sentSecondRun = app(TrialOnboardingService::class)->processAllTrialTenants();
        $this->assertSame(0, $sentSecondRun);
        Mail::assertSent(TrialOnboardingMail::class, 3);

        $this->assertContains(TrialOnboardingService::STEP_DAY_0, $day0->fresh()->trial_drip_steps_sent);
        $this->assertContains(TrialOnboardingService::STEP_DAY_11, $day11->fresh()->trial_drip_steps_sent);
        $this->assertContains(TrialOnboardingService::STEP_DAY_13, $day13->fresh()->trial_drip_steps_sent);
    }

    public function test_branded_customer_trip_portal_and_one_click_quote_approval(): void
    {
        $tenant = Tenant::create([
            'name' => 'Skyline Tours',
            'email' => 'hello@skyline.test',
            'phone' => '0507778899',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
        ]);

        $owner = User::create([
            'name' => 'Skyline Owner',
            'email' => 'owner@skyline.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Noam Levi',
            'email' => 'noam@client.test',
            'phone' => '0523334455',
        ]);

        $trip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'customer_id' => $customer->id,
            'title' => 'Greece Family Package',
            'destination' => 'Athens, Greece',
            'start_date' => now()->addDays(20)->toDateString(),
            'end_date' => now()->addDays(26)->toDateString(),
            'total_amount' => 12000,
            'supplier_name' => 'Secret Wholesale Supplier',
            'supplier_cost' => 8500,
            'status' => Trip::STATUS_QUOTE,
        ]);

        Payment::create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'amount' => 3000,
            'method' => 'credit',
            'paid_at' => now(),
        ]);

        $this->assertNotEmpty($trip->public_token);

        // 1. Public portal renders branded details, hides secret supplier cost, increments view counter
        $this->get("/t/{$trip->public_token}")
            ->assertOk()
            ->assertSee('Skyline Tours')
            ->assertSee('Greece Family Package')
            ->assertSee('Noam Levi')
            ->assertSee('12,000.00')
            ->assertSee('אשר הצעת מחיר והזמן')
            ->assertDontSee('Secret Wholesale Supplier')
            ->assertDontSee('8500');

        $this->assertSame(1, $trip->fresh()->portal_views_count);

        // 2. Public itinerary PDF downloads cleanly
        $this->get("/t/{$trip->public_token}/itinerary")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // 3. Customer clicks 1-click Quote Approval -> converts quote to confirmed + notifies owner!
        $this->post("/t/{$trip->public_token}/approve-quote", [
            'approver_name' => 'Noam Levi',
        ])->assertRedirect("/t/{$trip->public_token}");

        $trip->refresh();
        $this->assertSame(Trip::STATUS_CONFIRMED, $trip->status);
        $this->assertSame('Noam Levi', $trip->quote_approved_by_name);
        $this->assertNotNull($trip->quote_approved_at);
        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_admin_can_edit_trial_templates_send_manually_and_broadcast_updates(): void
    {
        Mail::fake();
        \App\Models\TrialEmailTemplate::ensureDefaultsExist();

        $template = \App\Models\TrialEmailTemplate::where('step', 'day_0')->firstOrFail();
        $template->update([
            'subject_he' => 'נושא מותאם אישית עבור {agency}',
            'heading_he' => 'כותרת מותאמת!',
        ]);

        $tenant = Tenant::create([
            'name' => 'Custom Mail Agency',
            'email' => 'custom@agency.test',
            'status' => 'active',
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(10),
            'language' => 'he',
        ]);

        // Manual send uses admin's customized subject/heading
        $sent = app(TrialOnboardingService::class)->sendTemplateManually($template, collect([$tenant]));
        $this->assertSame(1, $sent);
        $this->assertSame(1, $template->fresh()->sent_count);

        Mail::assertSent(TrialOnboardingMail::class, function (TrialOnboardingMail $mail) {
            return $mail->subjectLine === 'נושא מותאם אישית עבור Custom Mail Agency'
                && $mail->heading === 'כותרת מותאמת!';
        });

        // Test email mode works without requiring a tenant
        $testSent = app(TrialOnboardingService::class)->sendTemplateManually($template, collect(), 'qa@asafdigital.com', 'he');
        $this->assertSame(1, $testSent);

        // Admin trial email templates page renders cleanly
        $admin = User::create([
            'name' => 'Boss Admin',
            'email' => 'boss@templates.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/trial-email-templates')
            ->assertOk()
            ->assertSee('נושא מותאם אישית עבור {agency}');
    }
}
