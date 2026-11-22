<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PwaAndPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createAgentWithTenant(): array
    {
        $tenant = Tenant::create([
            'name' => 'Travel Agency',
            'email' => 'agency@example.com',
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

        return [$tenant, $agent];
    }

    public function test_pwa_manifest_and_service_worker_files_exist_and_page_renders(): void
    {
        [$tenant, $agent] = $this->createAgentWithTenant();

        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('images/pwa-icon-192.png'));
        $this->assertFileExists(public_path('images/pwa-icon-512.png'));
        $this->assertFileExists(public_path('images/apple-touch-icon.png'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertEquals('standalone', $manifest['display']);
        $this->assertEquals('#F59E0B', $manifest['theme_color']);

        $response = $this->actingAs($agent)->get('/agent/mobile-app-notifications');
        $response->assertOk();
        $response->assertSee('manifest.json', false);
        $response->assertSee('/sw.js', false);
    }

    public function test_authenticated_user_can_subscribe_and_unsubscribe_push_device(): void
    {
        [$tenant, $agent] = $this->createAgentWithTenant();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/example-token-123';

        $this->actingAs($agent)
            ->postJson(route('push.subscribe'), [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'BExamplePublicKey1234567890',
                    'auth' => 'ExampleAuthToken1234',
                ],
                'deviceLabel' => 'iPhone',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 1]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $agent->id,
            'tenant_id' => $tenant->id,
            'endpoint' => $endpoint,
        ]);

        $this->actingAs($agent)
            ->postJson(route('push.unsubscribe'), [
                'endpoint' => $endpoint,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 0]);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_collection_alerts_command_notifies_agent_for_unpaid_upcoming_trip(): void
    {
        [$tenant, $agent] = $this->createAgentWithTenant();

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'David Cohen',
        ]);

        $unpaidTrip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Amsterdam Trip',
            'destination' => 'Amsterdam',
            'start_date' => now()->addDays(4)->format('Y-m-d'),
            'total_amount' => 3500,
            'status' => 'confirmed',
        ]);

        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $unpaidTrip->id,
            'amount' => 1500,
            'method' => 'credit',
            'paid_at' => now()->format('Y-m-d'),
        ]);

        $mockPush = Mockery::mock(WebPushService::class);
        $mockPush->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function (User $u, string $title, string $body) use ($agent) {
                return $u->id === $agent->id
                    && str_contains($body, '2,000.00')
                    && str_contains($body, 'David Cohen');
            })
            ->andReturn(1);
        $this->app->instance(WebPushService::class, $mockPush);

        $this->artisan('app:check-collection-alerts')->assertSuccessful();
        $this->assertSame(1, $agent->notifications()->count());
    }

    public function test_upcoming_trips_command_sends_flight_push_alert(): void
    {
        [$tenant, $agent] = $this->createAgentWithTenant();

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Sarah Levi',
        ]);

        Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Vienna Flight',
            'destination' => 'Vienna',
            'start_date' => now()->addDay()->format('Y-m-d'),
            'total_amount' => 2000,
            'status' => 'confirmed',
            'flights' => [
                ['type' => 'outbound', 'airline' => 'El Al', 'flight_number' => 'LY361', 'departure_time' => now()->addDay()->setTime(9, 15)->toDateTimeString()],
            ],
        ]);

        $mockPush = Mockery::mock(WebPushService::class);
        $mockPush->shouldReceive('sendToUser')
            ->once()
            ->withArgs(function (User $u, string $title, string $body) use ($agent) {
                return $u->id === $agent->id
                    && str_contains($body, 'Sarah Levi')
                    && str_contains($body, 'LY361');
            })
            ->andReturn(1);
        $this->app->instance(WebPushService::class, $mockPush);

        $this->artisan('app:check-upcoming-trips')->assertSuccessful();
        $this->assertSame(1, $agent->notifications()->count());
    }
}
