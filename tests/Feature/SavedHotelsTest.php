<?php

namespace Tests\Feature;

use App\Filament\Agent\Resources\HotelResource;
use App\Filament\Agent\Resources\HotelResource\Pages\CreateHotel;
use App\Filament\Agent\Resources\HotelResource\Pages\ListHotels;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SavedHotelsTest extends TestCase
{
    use RefreshDatabase;

    private function createAgencyAndOwner(string $name, string $email): array
    {
        Plan::ensureDefaultPlansExist();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $tenant = Tenant::create([
            'name' => $name,
            'email' => $email,
            'status' => 'active',
            'plan_id' => $pro->id,
            'subscription_type' => 'lifetime',
            'language' => 'he',
            'currency' => 'ILS',
        ]);

        $owner = User::create([
            'name' => $name . ' Owner',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_OWNER,
            'tenant_id' => $tenant->id,
            'language' => 'he',
        ]);

        return [$tenant, $owner];
    }

    public function test_hotels_are_strictly_tenant_isolated_and_searchable_for_trip_picker(): void
    {
        [$tenantA, $ownerA] = $this->createAgencyAndOwner('Alpha Tours', 'alpha@hotels.test');
        [$tenantB, $ownerB] = $this->createAgencyAndOwner('Beta Tours', 'beta@hotels.test');

        Hotel::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Hilton Athens',
            'destination' => 'Athens, Greece',
            'stars' => 5,
            'default_supplier' => 'Booking.com',
            'is_active' => true,
        ]);

        Hotel::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Grand Hotel Rome',
            'destination' => 'Rome, Italy',
            'stars' => 4,
            'is_active' => true,
        ]);

        Hotel::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Archived Old Hotel',
            'destination' => 'Athens, Greece',
            'stars' => 3,
            'is_active' => false,
        ]);

        Hotel::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Secret Beta Resort',
            'destination' => 'Athens, Greece',
            'stars' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($ownerA);

        $options = Hotel::tripSelectOptions(null, 'Athens');
        $keys = array_keys($options);

        // Tenant A only sees active Tenant A hotels, with Athens hotel prioritized first
        $this->assertSame(['Hilton Athens', 'Grand Hotel Rome'], $keys);
        $this->assertStringContainsString('5★', $options['Hilton Athens']);
        $this->assertArrayNotHasKey('Archived Old Hotel', $options);
        $this->assertArrayNotHasKey('Secret Beta Resort', $options);

        // Search filters by name/destination/supplier
        $searchBySupplier = Hotel::tripSelectOptions('Booking');
        $this->assertSame(['Hilton Athens'], array_keys($searchBySupplier));

        // Tenant B only sees Tenant B's hotel
        $this->flushSession();
        $this->actingAs($ownerB);

        $betaOptions = Hotel::tripSelectOptions();
        $this->assertSame(['Secret Beta Resort'], array_keys($betaOptions));
    }

    public function test_agent_can_manage_saved_hotels_via_filament_resource_and_staff_delete_is_guarded(): void
    {
        [$tenant, $owner] = $this->createAgencyAndOwner('Sunrise Travel', 'owner@sunrise.test');

        $staff = User::create([
            'name' => 'Sales Rep',
            'email' => 'rep@sunrise.test',
            'password' => Hash::make('password123'),
            'role' => 'agent',
            'agency_role' => User::AGENCY_ROLE_STAFF,
            'permissions' => [],
            'tenant_id' => $tenant->id,
            'language' => 'he',
        ]);

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('agent'));

        Livewire::test(CreateHotel::class)
            ->fillForm([
                'name' => 'Dan Eilat',
                'destination' => 'Eilat',
                'stars' => 5,
                'phone' => '08-6362222',
                'default_supplier' => 'Dan Hotels Direct',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $hotel = Hotel::where('name', 'Dan Eilat')->first();
        $this->assertNotNull($hotel);
        $this->assertSame($tenant->id, $hotel->tenant_id);
        $this->assertSame(5, $hotel->stars);

        $this->get('/agent/hotels')
            ->assertOk()
            ->assertSee('Dan Eilat');

        Livewire::test(ListHotels::class)
            ->assertCanSeeTableRecords([$hotel]);

        $this->assertTrue(HotelResource::canDelete($hotel));

        // Staff without delete permission cannot delete saved hotels
        $this->flushSession();
        $this->actingAs($staff);
        $this->assertFalse(HotelResource::canDelete($hotel));
    }

    public function test_trip_form_supports_saved_hotels_and_legacy_free_text_hotels_on_portal(): void
    {
        [$tenant, $owner] = $this->createAgencyAndOwner('Med Tours', 'owner@medtours.test');

        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Noam Levi',
            'email' => 'noam@client.test',
            'phone' => '0501234567',
        ]);

        Hotel::create([
            'tenant_id' => $tenant->id,
            'name' => 'Four Seasons Limassol',
            'destination' => 'Limassol, Cyprus',
            'stars' => 5,
            'is_active' => true,
        ]);

        // Trip combining a saved hotel + a legacy free-text hotel not in the hotels table
        $trip = Trip::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Cyprus Family Vacation',
            'destination' => 'Limassol, Cyprus',
            'start_date' => now()->addDays(15)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'total_amount' => 12500,
            'status' => 'confirmed',
            'hotels' => [
                [
                    'name' => 'Four Seasons Limassol',
                    'from' => now()->addDays(15)->toDateString(),
                    'until' => now()->addDays(18)->toDateString(),
                    'rooms' => 2,
                ],
                [
                    'name' => 'Legacy Boutique Inn',
                    'from' => now()->addDays(18)->toDateString(),
                    'until' => now()->addDays(20)->toDateString(),
                    'rooms' => 1,
                ],
            ],
        ]);

        $this->actingAs($owner);

        $this->get('/agent/trips/create')->assertOk();
        $this->get('/agent/trips/' . $trip->id . '/edit')
            ->assertOk()
            ->assertSee('Four Seasons Limassol')
            ->assertSee('Legacy Boutique Inn');

        // Public customer portal continues rendering both hotels identically
        $this->get('/t/' . $trip->getOrCreatePublicToken())
            ->assertOk()
            ->assertSee('Four Seasons Limassol')
            ->assertSee('Legacy Boutique Inn');
    }
}
