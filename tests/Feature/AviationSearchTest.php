<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\AviationDirectoryService;
use App\Services\TripMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AviationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_searches_airports_airlines_and_destinations_locally_and_via_remote_api(): void
    {
        Http::fake([
            'autocomplete.travelpayouts.com/*' => Http::response([
                [
                    'code' => 'OKA',
                    'name' => 'Naha Airport',
                    'city_name' => 'Okinawa',
                    'country_name' => 'Japan',
                ],
            ], 200),
        ]);

        app()->setLocale('he');
        $service = app(AviationDirectoryService::class);

        // Local multilingual airport search (Hebrew + IATA)
        $tlvResults = $service->searchAirports('תל אביב');
        $this->assertNotEmpty($tlvResults);
        $this->assertStringContainsString('TLV', array_key_first($tlvResults));

        $fcoResults = $service->searchAirports('FCO');
        $this->assertNotEmpty($fcoResults);
        $this->assertStringContainsString('FCO', array_key_first($fcoResults));

        // Remote API enrichment for airport outside local catalog
        $okaResults = $service->searchAirports('Okinawa');
        $this->assertNotEmpty($okaResults);
        $this->assertStringContainsString('OKA', array_key_first($okaResults));

        // Airlines search (Hebrew + English + IATA code extraction)
        $elalResults = $service->searchAirlines('אל על');
        $this->assertNotEmpty($elalResults);
        $firstAirline = array_key_first($elalResults);
        $this->assertSame('LY', $service->extractAirlineCode($firstAirline));

        // Destinations search
        $romeDest = $service->searchDestinations('רומא');
        $this->assertNotEmpty($romeDest);
        $this->assertStringContainsString('רומא', array_key_first($romeDest));
    }

    public function test_authenticated_aviation_json_endpoints_and_trip_message_with_airports(): void
    {
        Http::fake([
            'autocomplete.travelpayouts.com/*' => Http::response([], 200),
        ]);

        $tenant = Tenant::create(['name' => 'Aero Agency', 'status' => 'active', 'language' => 'he']);
        $agent = User::create([
            'name' => 'Tom Agent',
            'email' => 'tom@aero.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($agent)
            ->getJson('/aviation/airports?q=TLV')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->actingAs($agent)
            ->getJson('/aviation/airlines?q=Wizz')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->actingAs($agent)
            ->getJson('/aviation/destinations?q=Amsterdam')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Yossi', 'phone' => '0501112222']);
        $trip = Trip::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'טיול לאמסטרדם',
            'destination' => 'אמסטרדם, Netherlands',
            'total_amount' => 4500,
            'status' => 'confirmed',
            'flights' => [
                [
                    'type' => 'outbound',
                    'airline' => 'LY — אל על (EL AL Israel Airlines)',
                    'flight_number' => 'LY337',
                    'from_airport' => 'TLV — תל אביב (בן גוריון), Israel',
                    'to_airport' => 'AMS — אמסטרדם (סכיפהול), Netherlands',
                    'departure_time' => '2026-12-10 08:30:00',
                    'arrival_time' => '2026-12-10 12:45:00',
                ],
            ],
        ]);

        $msg = app(TripMessageService::class)->buildMessage($trip, TripMessageService::TEMPLATE_TRIP_SUMMARY, 'he');
        $this->assertStringContainsString('TLV', $msg['body']);
        $this->assertStringContainsString('AMS', $msg['body']);
        $this->assertStringContainsString('LY337', $msg['body']);
    }
}
