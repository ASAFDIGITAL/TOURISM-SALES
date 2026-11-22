<?php

namespace Tests\Feature;

use App\Mail\TripMessageMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\ReminderRuleLog;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReminderRuleEngine;
use App\Services\TripMessageService;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class SmartRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function seedAgency(): array
    {
        $tenant = Tenant::create([
            'name' => 'Smart Travel',
            'email' => 'smart@example.com',
            'phone' => '050-1234567',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => 'he',
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $tenant->id,
            'language' => 'he',
            'notify_collections' => true,
            'notify_flights' => true,
            'notify_reminders' => true,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Yossi Levi',
            'email' => 'yossi@example.com',
            'phone' => '052-5551122',
        ]);

        return [$tenant, $agent, $customer];
    }

    public function test_reminder_rule_engine_fires_payment_and_trip_rules_once_without_duplicates(): void
    {
        Mail::fake();
        Http::fake([
            'capi.inforu.co.il/*' => Http::response(['StatusId' => 1], 200),
        ]);
        config()->set('services.inforu.basic_auth', 'dGVzdDp0b2tlbg==');

        [$tenant, $agent, $customer] = $this->seedAgency();

        // Unpaid trip departing in 7 days
        $unpaidTrip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Rome Holiday',
            'destination' => 'Rome',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'total_amount' => 5000,
            'status' => 'confirmed',
        ]);

        // Fully paid trip departing in 7 days (should NOT trigger payment rule!)
        $paidTrip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Athens Paid Trip',
            'destination' => 'Athens',
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'total_amount' => 3000,
            'status' => 'confirmed',
        ]);
        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $paidTrip->id,
            'amount' => 3000,
            'method' => 'credit',
            'paid_at' => now()->format('Y-m-d'),
        ]);

        // Rule 1: 7 days before unpaid departure -> both agent (Push) & customer (Email + SMS)
        $paymentRule = ReminderRule::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'תזכורת גבייה 7 ימים לפני',
            'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
            'days_offset' => 7,
            'recipient_type' => 'both',
            'channel' => 'all',
            'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
            'message_language' => 'he',
            'is_active' => true,
        ]);

        $mockPush = Mockery::mock(WebPushService::class);
        $mockPush->shouldReceive('sendToUser')->once()->andReturn(1);
        $this->app->instance(WebPushService::class, $mockPush);

        // First run triggers ONLY for unpaidTrip
        $this->artisan('app:run-reminder-rules')->assertSuccessful();

        $this->assertSame(1, ReminderRuleLog::where('reminder_rule_id', $paymentRule->id)->count());
        $this->assertDatabaseHas('reminder_rule_logs', [
            'reminder_rule_id' => $paymentRule->id,
            'trip_id' => $unpaidTrip->id,
        ]);
        $this->assertDatabaseMissing('reminder_rule_logs', [
            'reminder_rule_id' => $paymentRule->id,
            'trip_id' => $paidTrip->id,
        ]);

        Mail::assertSent(TripMessageMail::class, 1);
        Http::assertSentCount(1);

        // Second run must NOT duplicate
        $this->artisan('app:run-reminder-rules')->assertSuccessful();
        $this->assertSame(1, ReminderRuleLog::where('reminder_rule_id', $paymentRule->id)->count());
    }

    public function test_custom_placeholders_and_default_rules_creation_and_pages_work(): void
    {
        [$tenant, $agent, $customer] = $this->seedAgency();

        $trip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Madrid Weekend',
            'destination' => 'Madrid',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'total_amount' => 4000,
            'status' => 'confirmed',
        ]);

        $engine = app(ReminderRuleEngine::class);
        $rendered = $engine->replacePlaceholders('שלום {customer}, יתרה ל-{trip} ({destination}): {balance}', $trip);
        $this->assertStringContainsString('Yossi Levi', $rendered);
        $this->assertStringContainsString('Madrid Weekend', $rendered);
        $this->assertStringContainsString('4,000.00', $rendered);

        $created = $engine->createDefaultRulesForTenant($tenant->id, 'he');
        $this->assertSame(4, $created);

        $this->actingAs($agent)->get('/agent/reminder-rules')->assertOk();
        $this->actingAs($agent)->get('/agent/reminders')->assertOk();
    }

    public function test_manual_customer_reminder_delivers_email_on_schedule(): void
    {
        Mail::fake();
        [$tenant, $agent, $customer] = $this->seedAgency();

        $trip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'London Trip',
            'destination' => 'London',
            'total_amount' => 2500,
            'status' => 'confirmed',
        ]);

        $reminder = Reminder::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'category' => 'payment',
            'recipient_type' => 'customer',
            'channel' => 'email',
            'message_language' => 'he',
            'scheduled_at' => now()->subMinute(),
            'status' => 'pending',
            'message' => 'נא להסדיר יתרת תשלום.',
        ]);

        $this->artisan('app:check-reminders')->assertSuccessful();

        Mail::assertSent(TripMessageMail::class, 1);
        $this->assertEquals('sent', $reminder->fresh()->status);
    }
}
