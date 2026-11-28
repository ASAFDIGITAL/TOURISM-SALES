<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\SalesReports;
use App\Mail\TripMessageMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\Reminder;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReceiptService;
use App\Services\TripDocumentationService;
use App\Services\TripMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TripMessagingAndItineraryTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleTrip(string $lang = 'he'): Trip
    {
        $tenant = Tenant::create([
            'name' => $lang === 'ar' ? 'وكالة السفر' : 'אסף טורס',
            'email' => 'agency@example.com',
            'phone' => '050-1234567',
            'status' => 'active',
            'currency' => 'ILS',
            'language' => $lang,
            'receipt_prefix' => 'REC',
            'receipt_next_number' => 1001,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $lang === 'ar' ? 'أحمد علي' : 'דוד כהן',
            'phone' => '052-9876543',
            'email' => 'customer@example.com',
        ]);

        $trip = Trip::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'title' => $lang === 'ar' ? 'رحلة سانتوريني' : 'חופשה בסנטוריני',
            'destination' => 'Santorini',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-15',
            'total_amount' => 10000,
            'status' => 'confirmed',
            'hotels' => [
                ['name' => 'Katikies Hotel', 'from' => '2026-07-10', 'until' => '2026-07-15', 'rooms' => 2],
            ],
            'flights' => [
                ['type' => 'outbound', 'airline' => 'El Al', 'flight_number' => 'LY541', 'departure_time' => '2026-07-10 08:30:00'],
            ],
            'passengers' => [
                ['name' => 'David Cohen'],
                ['name' => 'Sarah Cohen'],
            ],
            'trip_summary' => '<p>Breakfast included.</p>',
        ]);

        Payment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'trip_id' => $trip->id,
            'amount' => 3500,
            'method' => 'credit',
            'paid_at' => '2026-06-01',
        ]);

        return $trip->fresh(['tenant', 'customer', 'payments']);
    }

    public function test_itinerary_pdf_renders_cleanly_in_hebrew_arabic_and_english(): void
    {
        $service = app(TripDocumentationService::class);

        foreach (['he', 'ar', 'en'] as $lang) {
            $trip = $this->createSampleTrip($lang);
            $pdf = $service->getItineraryPdfContent($trip);

            $this->assertNotEmpty($pdf);
            $this->assertStringStartsWith('%PDF-', $pdf);
        }

        // Arabic contextual glyph shaping connects letters (Presentation Forms-B) and forms Lam-Alef ligatures
        $shapedArabic = \App\Helpers\PdfHelper::fixRtl('السلام عليكم');
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $shapedArabic);
        $this->assertStringContainsString("\u{FEFC}", $shapedArabic); // final Lam-Alef ligature in السلام

        // Hebrew embedded inside an English (LTR) PDF is visually reversed so "פריז, France" never prints backwards
        $fixedMixedLtr = \App\Helpers\PdfHelper::fixForPdf('פריז, France', false);
        $this->assertSame('זירפ, France', $fixedMixedLtr);

        // Downloading PDF in an explicit override language works regardless of tenant default language
        $heTrip = $this->createSampleTrip('he');
        $heTrip->update([
            'destination' => 'פריז, France',
            'hotels' => [
                [
                    'type' => 'apartment',
                    'name' => 'Champs-Élysées Suite',
                    'address' => '18 Rue Marbeuf, Paris',
                    'from' => '2026-07-10',
                    'until' => '2026-07-15',
                    'rooms' => 2,
                ],
            ],
            'passengers' => [
                ['name' => 'דוד כהן', 'passport_number' => 'IL99887766'],
            ],
        ]);
        $heTrip->refresh();

        foreach (['he', 'ar', 'en'] as $overrideLang) {
            $overridePdf = $service->getItineraryPdfContent($heTrip, $overrideLang);
            $this->assertStringStartsWith('%PDF-', $overridePdf);

            $this->get(route('trip.portal.itinerary', ['token' => $heTrip->getOrCreatePublicToken(), 'lang' => $overrideLang]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }

        $this->get($heTrip->getPortalUrl())
            ->assertOk()
            ->assertSee('Champs-Élysées Suite')
            ->assertSee('18 Rue Marbeuf, Paris')
            ->assertSee('IL99887766');
    }

    public function test_builds_hebrew_and_arabic_trip_summary_and_payment_reminder_messages(): void
    {
        $trip = $this->createSampleTrip('he');
        $service = app(TripMessageService::class);

        // Hebrew trip summary
        $heSummary = $service->buildMessage($trip, TripMessageService::TEMPLATE_TRIP_SUMMARY, 'he');
        $this->assertStringContainsString('סיכום נסיעה', $heSummary['subject']);
        $this->assertStringContainsString('דוד כהן', $heSummary['body']);
        $this->assertStringContainsString('Katikies Hotel', $heSummary['body']);
        $this->assertStringContainsString('LY541', $heSummary['body']);

        // Arabic trip summary
        $arSummary = $service->buildMessage($trip, TripMessageService::TEMPLATE_TRIP_SUMMARY, 'ar');
        $this->assertStringContainsString('ملخص الرحلة', $arSummary['subject']);
        $this->assertStringContainsString('الفنادق', $arSummary['body']);
        $this->assertStringContainsString('الرحلات الجوية', $arSummary['body']);

        // Hebrew payment reminder (balance = 10,000 - 3,500 = 6,500)
        $hePayment = $service->buildMessage($trip, TripMessageService::TEMPLATE_PAYMENT_REMINDER, 'he');
        $this->assertStringContainsString('תזכורת לתשלום', $hePayment['subject']);
        $this->assertStringContainsString('6,500.00', $hePayment['body']);

        // Arabic payment reminder
        $arPayment = $service->buildMessage($trip, TripMessageService::TEMPLATE_PAYMENT_REMINDER, 'ar');
        $this->assertStringContainsString('تذكير بالدفع', $arPayment['subject']);
        $this->assertStringContainsString('6,500.00', $arPayment['body']);
    }

    public function test_normalizes_israeli_phone_number_for_whatsapp(): void
    {
        $service = app(TripMessageService::class);

        $url = $service->buildWhatsAppUrl('052-987-6543', 'שלום');
        $this->assertStringStartsWith('https://api.whatsapp.com/send?phone=972529876543&text=', $url);
    }

    public function test_sends_email_with_optional_pdf_attachment_and_logs_reminder(): void
    {
        Mail::fake();

        $trip = $this->createSampleTrip('he');
        $service = app(TripMessageService::class);
        $built = $service->buildMessage($trip, TripMessageService::TEMPLATE_TRIP_SUMMARY, 'he');

        $service->sendEmail(
            trip: $trip,
            recipientEmail: 'customer@example.com',
            subject: $built['subject'],
            body: $built['body'],
            locale: 'he',
            attachItinerary: true,
        );

        Mail::assertSent(TripMessageMail::class, function (TripMessageMail $mail) use ($trip) {
            return $mail->hasTo('customer@example.com')
                && $mail->hasFrom(config('mail.from.address'), $trip->tenant->name)
                && $mail->hasReplyTo('agency@example.com', $trip->tenant->name)
                && count($mail->attachments()) === 1;
        });

        $this->assertDatabaseHas('reminders', [
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_sales_report_downloads_pdf_and_csv_and_receipt_renders(): void
    {
        $trip = $this->createSampleTrip('he');
        $agent = User::factory()->create([
            'role' => 'agent',
            'tenant_id' => $trip->tenant_id,
            'language' => 'he',
        ]);

        // Receipt PDF renders cleanly
        $receipt = Receipt::withoutGlobalScopes()->create([
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'receipt_number' => 'REC-1001',
        ]);
        $trip->payments->first()->update(['receipt_id' => $receipt->id]);
        $receiptPdf = app(ReceiptService::class)->getPdfContent($receipt->fresh(['payments', 'trip.customer', 'tenant']));
        $this->assertStringStartsWith('%PDF-', $receiptPdf);

        // Sales report PDF & CSV download actions work
        Livewire::actingAs($agent)
            ->test(SalesReports::class)
            ->call('downloadPdf')
            ->assertFileDownloaded();

        Livewire::actingAs($agent)
            ->test(SalesReports::class)
            ->call('downloadCsv')
            ->assertFileDownloaded();
    }
}
