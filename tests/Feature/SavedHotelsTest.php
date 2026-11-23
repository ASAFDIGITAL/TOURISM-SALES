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

    public function test_hotels_excel_template_download_and_xlsx_csv_import(): void
    {
        [$tenantA, $ownerA] = $this->createAgencyAndOwner('Excel Tours', 'excel@tours.test');
        [$tenantB, $ownerB] = $this->createAgencyAndOwner('Other Tours', 'other@tours.test');

        $service = app(\App\Services\HotelSpreadsheetService::class);

        // 1. Generate real .xlsx template in Hebrew, Arabic, and English
        foreach (['he', 'ar', 'en'] as $lang) {
            $xlsxBinary = $service->generateTemplateXlsx($lang);
            $this->assertStringStartsWith("PK\x03\x04", $xlsxBinary);
            $this->assertGreaterThan(1000, strlen($xlsxBinary));
        }

        // 2. Round-trip import generated Hebrew .xlsx into Tenant A
        $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_hotels_') . '.xlsx';
        file_put_contents($tmpXlsx, $service->generateTemplateXlsx('he'));

        try {
            $firstImport = $service->importFromPath($tmpXlsx, $tenantA->id, true);
            $this->assertSame(2, $firstImport['created']);
            $this->assertSame(0, $firstImport['updated']);
            $this->assertSame(0, $firstImport['skipped']);

            $hilton = Hotel::withoutGlobalScopes()
                ->where('tenant_id', $tenantA->id)
                ->where('name', 'Hilton Athens')
                ->first();
            $this->assertNotNull($hilton);
            $this->assertSame(5, $hilton->stars);
            $this->assertSame('Booking.com', $hilton->default_supplier);

            // Tenant B has zero hotels from Tenant A's import
            $this->assertSame(0, Hotel::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());

            // 3. Re-import with updateExisting = false skips duplicates
            $skipImport = $service->importFromPath($tmpXlsx, $tenantA->id, false);
            $this->assertSame(0, $skipImport['created']);
            $this->assertSame(2, $skipImport['skipped']);

            // 4. Re-import with updateExisting = true updates existing records
            $updateImport = $service->importFromPath($tmpXlsx, $tenantA->id, true);
            $this->assertSame(0, $updateImport['created']);
            $this->assertSame(2, $updateImport['updated']);
        } finally {
            @unlink($tmpXlsx);
        }

        // 5. Arabic UTF-8 BOM semicolon CSV import with star symbols
        $tmpCsv = tempnam(sys_get_temp_dir(), 'test_hotels_') . '.csv';
        $csvContent = "\xEF\xBB\xBF"
            . "اسم الفندق;الوجهة / المدينة;النجوم (1-5);الهاتف;البريد الإلكتروني;العنوان;المزوّد الافتراضي;ملاحظات\n"
            . "فندق ريتز كارلتون;دبي;★★★★★;+971-4-3994000;res@ritz.example;JBR Walk;Direct;شامل الإفطار\n";
        file_put_contents($tmpCsv, $csvContent);

        try {
            $csvResult = $service->importFromPath($tmpCsv, $tenantB->id, true);
            $this->assertSame(1, $csvResult['created']);

            $ritz = Hotel::withoutGlobalScopes()
                ->where('tenant_id', $tenantB->id)
                ->where('name', 'فندق ريتز كارلتون')
                ->first();
            $this->assertNotNull($ritz);
            $this->assertSame(5, $ritz->stars);
            $this->assertSame('دبي', $ritz->destination);
            $this->assertSame('Direct', $ritz->default_supplier);
        } finally {
            @unlink($tmpCsv);
        }

        // 6. Verify Filament ListHotels page shows Download & Import actions
        $this->actingAs($ownerA);
        Filament::setCurrentPanel(Filament::getPanel('agent'));

        Livewire::test(ListHotels::class)
            ->assertActionExists('download_excel_template')
            ->assertActionExists('import_hotels_excel');
    }
}
