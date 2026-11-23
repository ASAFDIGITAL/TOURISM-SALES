<?php

namespace App\Services;

use App\Mail\TripMessageMail;
use App\Models\Reminder;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TripMessageService
{
    public const TEMPLATE_TRIP_SUMMARY = 'trip_summary';
    public const TEMPLATE_PAYMENT_REMINDER = 'payment_reminder';
    public const TEMPLATE_PRE_TRIP_REMINDER = 'pre_trip_reminder';
    public const TEMPLATE_CUSTOM = 'custom';

    private ?Reminder $lastEmailReminder = null;

    public function getLastEmailReminder(): ?Reminder
    {
        return $this->lastEmailReminder;
    }

    /**
     * Build a localized subject & body for the requested message template.
     *
     * @return array{subject: string, body: string}
     */
    public function buildMessage(Trip $trip, string $template, string $locale = 'he'): array
    {
        $locale = in_array($locale, ['he', 'ar', 'en'], true) ? $locale : 'he';

        return match ($template) {
            self::TEMPLATE_PAYMENT_REMINDER => $this->buildPaymentReminder($trip, $locale),
            self::TEMPLATE_PRE_TRIP_REMINDER => $this->buildPreTripReminder($trip, $locale),
            self::TEMPLATE_CUSTOM => $this->buildCustomTemplate($trip, $locale),
            default => $this->buildTripSummary($trip, $locale),
        };
    }

    public function buildWhatsAppUrl(string $phone, string $message): string
    {
        $normalized = $this->normalizePhoneForWhatsApp($phone);

        return 'https://api.whatsapp.com/send?phone=' . $normalized . '&text=' . rawurlencode($message);
    }

    public function normalizePhoneForWhatsApp(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 10) {
            // Default local Israeli number (e.g. 050-1234567 -> 972501234567)
            $digits = '972' . substr($digits, 1);
        }

        return $digits;
    }

    public function sendEmail(
        Trip $trip,
        string $recipientEmail,
        string $subject,
        string $body,
        string $locale = 'he',
        bool $attachItinerary = false,
        ?string $senderName = null,
        bool $logReminder = true,
        ?Reminder $existingReminder = null,
    ): bool {
        $this->lastEmailReminder = null;

        $tenant = $trip->tenant;
        if ($tenant && ! $tenant->hasMonthlyEmailQuota()) {
            return false;
        }

        $trackingToken = Str::random(40);
        $trackingUrl = route('email.track.open', ['token' => $trackingToken]);

        $reminder = $existingReminder;
        if ($reminder) {
            $reminder->forceFill([
                'recipient_email' => $recipientEmail,
                'tracking_token' => $trackingToken,
            ])->save();
        } elseif ($logReminder) {
            $reminder = Reminder::create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'recipient_type' => 'customer',
                'channel' => 'email',
                'recipient_email' => $recipientEmail,
                'tracking_token' => $trackingToken,
                'message_language' => $locale,
                'scheduled_at' => now(),
                'status' => 'pending',
                'message' => $body,
            ]);
        }

        $pdfContent = null;
        $pdfFilename = null;

        if ($attachItinerary) {
            $pdfContent = app(TripDocumentationService::class)->getItineraryPdfContent($trip);
            $safeTitle = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $trip->title ?: 'Trip');
            $pdfFilename = "{$safeTitle}_Itinerary.pdf";
        }

        try {
            Mail::to($recipientEmail)->send(new TripMessageMail(
                trip: $trip,
                subjectLine: $subject,
                bodyText: $body,
                localeCode: $locale,
                pdfContent: $pdfContent,
                pdfFilename: $pdfFilename,
                senderName: $senderName ?: $trip->tenant?->name,
                trackingUrl: $trackingUrl,
            ));
        } catch (\Throwable $e) {
            if ($reminder) {
                $reminder->forceFill([
                    'status' => 'failed',
                    'error_message' => Str::limit($e->getMessage(), 500),
                ])->save();
                $this->lastEmailReminder = $reminder;
            }

            return false;
        }

        if ($reminder) {
            $reminder->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ])->save();
            $this->lastEmailReminder = $reminder;
        }

        return true;
    }

    public function logSentMessage(Trip $trip, string $channel, string $body): Reminder
    {
        return Reminder::create([
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'channel' => $channel,
            'scheduled_at' => now(),
            'status' => 'sent',
            'message' => $body,
        ]);
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function buildTripSummary(Trip $trip, string $locale): array
    {
        $customerName = $trip->customer?->name ?? '';
        $tenantName = $trip->tenant?->name ?? '';
        $tenantPhone = $trip->tenant?->phone ?? '';
        $dateRange = $this->formatDateRange($trip);

        $flights = is_array($trip->flights) ? $trip->flights : [];
        $hotels = is_array($trip->hotels) ? $trip->hotels : [];
        $passengers = is_array($trip->passengers) ? $trip->passengers : [];
        $summaryText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $trip->trip_summary)));

        if ($locale === 'ar') {
            $subject = "ملخص الرحلة: {$trip->title}";
            $lines = [
                $customerName ? "مرحباً {$customerName}،" : 'مرحباً،',
                'فيما يلي ملخص تفاصيل رحلتك:',
                '',
                "*الرحلة:* {$trip->title}",
            ];
            if ($trip->destination) {
                $lines[] = "*الوجهة:* {$trip->destination}";
            }
            if ($dateRange !== '') {
                $lines[] = "*التواريخ:* {$dateRange}";
            }
            if (count($flights) > 0) {
                $lines[] = '';
                $lines[] = '*الرحلات الجوية:*';
                foreach ($flights as $flight) {
                    $typeLabel = match ($flight['type'] ?? 'outbound') {
                        'return' => 'عودة',
                        'connection' => 'ترانزيت',
                        default => 'ذهاب',
                    };
                    $dep = !empty($flight['departure_time']) ? Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') : '';
                    $route = (!empty($flight['from_airport']) || !empty($flight['to_airport']))
                        ? trim(($flight['from_airport'] ?? '') . ' -> ' . ($flight['to_airport'] ?? ''), ' ->')
                        : null;
                    $parts = array_filter([$typeLabel, $route, $flight['airline'] ?? null, $flight['flight_number'] ?? null, $dep]);
                    $lines[] = '- ' . implode(' | ', $parts);
                }
            }
            if (count($hotels) > 0) {
                $lines[] = '';
                $lines[] = '*الفنادق:*';
                foreach ($hotels as $hotel) {
                    $from = !empty($hotel['from']) ? Carbon::parse($hotel['from'])->format('d/m/Y') : '';
                    $until = !empty($hotel['until']) ? Carbon::parse($hotel['until'])->format('d/m/Y') : '';
                    $dates = ($from && $until) ? "{$from} - {$until}" : ($from ?: $until);
                    $rooms = !empty($hotel['rooms']) ? "{$hotel['rooms']} غرف" : '';
                    $parts = array_filter([$hotel['name'] ?? null, $dates, $rooms]);
                    $lines[] = '- ' . implode(' | ', $parts);
                }
            }
            if (count($passengers) > 0) {
                $names = array_filter(array_map(fn ($p) => $p['name'] ?? null, $passengers));
                if (count($names) > 0) {
                    $lines[] = '';
                    $lines[] = '*المسافرون (' . count($names) . '):* ' . implode('، ', $names);
                }
            }
            if ($summaryText !== '') {
                $lines[] = '';
                $lines[] = "*ملاحظات إضافية:*\n{$summaryText}";
            }
            $lines[] = '';
            $lines[] = '*رابط تفاصيل الرحلة والمستندات:* ' . $trip->getPortalUrl();
            $lines[] = 'نتمنى لك رحلة سعيدة وممتعة!';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        if ($locale === 'en') {
            $subject = "Trip Summary: {$trip->title}";
            $lines = [
                $customerName ? "Hello {$customerName}," : 'Hello,',
                'Here is your trip summary:',
                '',
                "*Trip:* {$trip->title}",
            ];
            if ($trip->destination) {
                $lines[] = "*Destination:* {$trip->destination}";
            }
            if ($dateRange !== '') {
                $lines[] = "*Dates:* {$dateRange}";
            }
            if (count($flights) > 0) {
                $lines[] = '';
                $lines[] = '*Flights:*';
                foreach ($flights as $flight) {
                    $typeLabel = ucfirst($flight['type'] ?? 'outbound');
                    $dep = !empty($flight['departure_time']) ? Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') : '';
                    $route = (!empty($flight['from_airport']) || !empty($flight['to_airport']))
                        ? trim(($flight['from_airport'] ?? '') . ' -> ' . ($flight['to_airport'] ?? ''), ' ->')
                        : null;
                    $parts = array_filter([$typeLabel, $route, $flight['airline'] ?? null, $flight['flight_number'] ?? null, $dep]);
                    $lines[] = '- ' . implode(' | ', $parts);
                }
            }
            if (count($hotels) > 0) {
                $lines[] = '';
                $lines[] = '*Hotels:*';
                foreach ($hotels as $hotel) {
                    $from = !empty($hotel['from']) ? Carbon::parse($hotel['from'])->format('d/m/Y') : '';
                    $until = !empty($hotel['until']) ? Carbon::parse($hotel['until'])->format('d/m/Y') : '';
                    $dates = ($from && $until) ? "{$from} - {$until}" : ($from ?: $until);
                    $rooms = !empty($hotel['rooms']) ? "{$hotel['rooms']} rooms" : '';
                    $parts = array_filter([$hotel['name'] ?? null, $dates, $rooms]);
                    $lines[] = '- ' . implode(' | ', $parts);
                }
            }
            if (count($passengers) > 0) {
                $names = array_filter(array_map(fn ($p) => $p['name'] ?? null, $passengers));
                if (count($names) > 0) {
                    $lines[] = '';
                    $lines[] = '*Passengers (' . count($names) . '):* ' . implode(', ', $names);
                }
            }
            if ($summaryText !== '') {
                $lines[] = '';
                $lines[] = "*Notes:*\n{$summaryText}";
            }
            $lines[] = '';
            $lines[] = '*Trip Portal & Documents:* ' . $trip->getPortalUrl();
            $lines[] = 'Have a wonderful trip!';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        // Default: Hebrew ('he')
        $subject = "סיכום נסיעה: {$trip->title}";
        $lines = [
            $customerName ? "שלום {$customerName}," : 'שלום,',
            'מצורף סיכום פרטי הנסיעה שלך:',
            '',
            "*טיול:* {$trip->title}",
        ];
        if ($trip->destination) {
            $lines[] = "*יעד:* {$trip->destination}";
        }
        if ($dateRange !== '') {
            $lines[] = "*תאריכים:* {$dateRange}";
        }
        if (count($flights) > 0) {
            $lines[] = '';
            $lines[] = '*טיסות:*';
            foreach ($flights as $flight) {
                $typeLabel = match ($flight['type'] ?? 'outbound') {
                    'return' => 'חזור',
                    'connection' => 'קונקשין',
                    default => 'הלוך',
                };
                $dep = !empty($flight['departure_time']) ? Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') : '';
                $route = (!empty($flight['from_airport']) || !empty($flight['to_airport']))
                    ? trim(($flight['from_airport'] ?? '') . ' -> ' . ($flight['to_airport'] ?? ''), ' ->')
                    : null;
                $parts = array_filter([$typeLabel, $route, $flight['airline'] ?? null, $flight['flight_number'] ?? null, $dep]);
                $lines[] = '- ' . implode(' | ', $parts);
            }
        }
        if (count($hotels) > 0) {
            $lines[] = '';
            $lines[] = '*בתי מלון:*';
            foreach ($hotels as $hotel) {
                $from = !empty($hotel['from']) ? Carbon::parse($hotel['from'])->format('d/m/Y') : '';
                $until = !empty($hotel['until']) ? Carbon::parse($hotel['until'])->format('d/m/Y') : '';
                $dates = ($from && $until) ? "{$from} - {$until}" : ($from ?: $until);
                $rooms = !empty($hotel['rooms']) ? "{$hotel['rooms']} חדרים" : '';
                $parts = array_filter([$hotel['name'] ?? null, $dates, $rooms]);
                $lines[] = '- ' . implode(' | ', $parts);
            }
        }
        if (count($passengers) > 0) {
            $names = array_filter(array_map(fn ($p) => $p['name'] ?? null, $passengers));
            if (count($names) > 0) {
                $lines[] = '';
                $lines[] = '*נוסעים (' . count($names) . '):* ' . implode(', ', $names);
            }
        }
        if ($summaryText !== '') {
            $lines[] = '';
            $lines[] = "*פרטים נוספים:*\n{$summaryText}";
        }
        $lines[] = '';
        $lines[] = '*לצפייה בפרטי הטיול ומסמכי הנסיעה:* ' . $trip->getPortalUrl();
        $lines[] = 'נסיעה טובה ומהנה!';
        $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

        return ['subject' => $subject, 'body' => implode("\n", $lines)];
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function buildPaymentReminder(Trip $trip, string $locale): array
    {
        $customerName = $trip->customer?->name ?? '';
        $tenantName = $trip->tenant?->name ?? '';
        $tenantPhone = $trip->tenant?->phone ?? '';
        $symbol = $this->currencySymbol($trip->tenant?->currency ?? 'ILS');

        $total = number_format((float) $trip->total_amount, 2);
        $paid = number_format((float) $trip->paid_amount, 2);
        $balance = number_format(max(0, (float) $trip->balance), 2);
        $startDate = $trip->start_date?->format('d/m/Y');

        if ($locale === 'ar') {
            $subject = "تذكير بالدفع: {$trip->title}";
            $lines = [
                $customerName ? "مرحباً {$customerName}،" : 'مرحباً،',
                'نود تذكيرك بخصوص الرصيد المتبقي لرحلتك:',
                '',
                "*الرحلة:* {$trip->title}" . ($trip->destination ? " ({$trip->destination})" : ''),
            ];
            if ($startDate) {
                $lines[] = "*تاريخ السفر:* {$startDate}";
            }
            $lines[] = '';
            $lines[] = "- *المبلغ الإجمالي:* {$symbol}{$total}";
            $lines[] = "- *المدفوع حتى الآن:* {$symbol}{$paid}";
            $lines[] = "- *الرصيد المتبقي للدفع:* {$symbol}{$balance}";
            $lines[] = '';
            $lines[] = 'يرجى ترتيب تسديد الرصيد المتبقي في أقرب وقت ممكن. لأي استفسار نحن هنا لخدمتك.';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        if ($locale === 'en') {
            $subject = "Payment Reminder: {$trip->title}";
            $lines = [
                $customerName ? "Hello {$customerName}," : 'Hello,',
                'This is a friendly reminder regarding the open balance for your upcoming trip:',
                '',
                "*Trip:* {$trip->title}" . ($trip->destination ? " ({$trip->destination})" : ''),
            ];
            if ($startDate) {
                $lines[] = "*Departure Date:* {$startDate}";
            }
            $lines[] = '';
            $lines[] = "- *Total Amount:* {$symbol}{$total}";
            $lines[] = "- *Paid So Far:* {$symbol}{$paid}";
            $lines[] = "- *Remaining Balance:* {$symbol}{$balance}";
            $lines[] = '';
            $lines[] = 'Please arrange payment of the remaining balance at your earliest convenience. Thank you!';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        // Default: Hebrew ('he')
        $subject = "תזכורת לתשלום: {$trip->title}";
        $lines = [
            $customerName ? "שלום {$customerName}," : 'שלום,',
            'תזכורת ידידותית לגבי יתרת התשלום הפתוחה עבור הטיול שלך:',
            '',
            "*טיול:* {$trip->title}" . ($trip->destination ? " ({$trip->destination})" : ''),
        ];
        if ($startDate) {
            $lines[] = "*תאריך יציאה:* {$startDate}";
        }
        $lines[] = '';
        $lines[] = "- *סכום כולל:* {$symbol}{$total}";
        $lines[] = "- *שולם עד כה:* {$symbol}{$paid}";
        $lines[] = "- *יתרה לתשלום:* {$symbol}{$balance}";
        $lines[] = '';
        $lines[] = 'נשמח להסדרת יתרת התשלום בהקדם. לכל שאלה אנחנו כאן לשירותך!';
        $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

        return ['subject' => $subject, 'body' => implode("\n", $lines)];
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function buildPreTripReminder(Trip $trip, string $locale): array
    {
        $customerName = $trip->customer?->name ?? '';
        $tenantName = $trip->tenant?->name ?? '';
        $tenantPhone = $trip->tenant?->phone ?? '';
        $startDate = $trip->start_date?->format('d/m/Y');
        $flights = is_array($trip->flights) ? $trip->flights : [];
        $firstFlight = $flights[0] ?? null;

        if ($locale === 'ar') {
            $subject = "تذكير قبل السفر: {$trip->title}";
            $lines = [
                $customerName ? "مرحباً {$customerName}،" : 'مرحباً،',
                'رحلتك تقترب! إليك بعض التذكيرات المهمة قبل السفر:',
                '',
                "*الرحلة:* {$trip->title}" . ($trip->destination ? " - {$trip->destination}" : ''),
            ];
            if ($startDate) {
                $lines[] = "*تاريخ المغادرة:* {$startDate}";
            }
            if ($firstFlight) {
                $dep = !empty($firstFlight['departure_time']) ? Carbon::parse($firstFlight['departure_time'])->format('d/m/Y H:i') : '';
                $parts = array_filter([$firstFlight['airline'] ?? null, $firstFlight['flight_number'] ?? null, $dep]);
                if (count($parts) > 0) {
                    $lines[] = '*تفاصيل الرحلة الأولى:* ' . implode(' | ', $parts);
                }
            }
            $lines[] = '';
            $lines[] = '- يرجى التأكد من صلاحية جواز السفر ووثائق السفر.';
            $lines[] = '- يوصى بالوصول إلى المطار قبل 3 ساعات على الأقل من موعد الإقلاع.';
            $lines[] = '';
            $lines[] = 'نتمنى لك سفراً آمناً ورحلة ممتعة!';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        if ($locale === 'en') {
            $subject = "Pre-Trip Reminder: {$trip->title}";
            $lines = [
                $customerName ? "Hello {$customerName}," : 'Hello,',
                'Your trip is coming up soon! Here are a few important reminders:',
                '',
                "*Trip:* {$trip->title}" . ($trip->destination ? " - {$trip->destination}" : ''),
            ];
            if ($startDate) {
                $lines[] = "*Departure Date:* {$startDate}";
            }
            if ($firstFlight) {
                $dep = !empty($firstFlight['departure_time']) ? Carbon::parse($firstFlight['departure_time'])->format('d/m/Y H:i') : '';
                $parts = array_filter([$firstFlight['airline'] ?? null, $firstFlight['flight_number'] ?? null, $dep]);
                if (count($parts) > 0) {
                    $lines[] = '*First Flight:* ' . implode(' | ', $parts);
                }
            }
            $lines[] = '';
            $lines[] = '- Please double-check your passport validity and travel documents.';
            $lines[] = '- Please arrive at the airport at least 3 hours before departure.';
            $lines[] = '';
            $lines[] = 'Wishing you a safe and wonderful journey!';
            $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        // Default: Hebrew ('he')
        $subject = "תזכורת לפני טיסה: {$trip->title}";
        $lines = [
            $customerName ? "שלום {$customerName}," : 'שלום,',
            'הטיול שלך ממש מתקרב! הנה כמה תזכורות חשובות לפני היציאה:',
            '',
            "*טיול:* {$trip->title}" . ($trip->destination ? " - {$trip->destination}" : ''),
        ];
        if ($startDate) {
            $lines[] = "*תאריך יציאה:* {$startDate}";
        }
        if ($firstFlight) {
            $dep = !empty($firstFlight['departure_time']) ? Carbon::parse($firstFlight['departure_time'])->format('d/m/Y H:i') : '';
            $parts = array_filter([$firstFlight['airline'] ?? null, $firstFlight['flight_number'] ?? null, $dep]);
            if (count($parts) > 0) {
                $lines[] = '*פרטי טיסה ראשונה:* ' . implode(' | ', $parts);
            }
        }
        $lines[] = '';
        $lines[] = '- נא לוודא שדרכונים ומסמכי הנסיעה בתוקף ובתיק.';
        $lines[] = '- מומלץ להגיע לשדה התעופה לפחות 3 שעות לפני ההמראה.';
        $lines[] = '';
        $lines[] = 'טיסה נעימה וחופשה מושלמת!';
        $lines[] = trim($tenantName . ($tenantPhone ? " | {$tenantPhone}" : ''));

        return ['subject' => $subject, 'body' => implode("\n", $lines)];
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function buildCustomTemplate(Trip $trip, string $locale): array
    {
        $customerName = $trip->customer?->name ?? '';
        $tenantName = $trip->tenant?->name ?? '';

        if ($locale === 'ar') {
            return [
                'subject' => $trip->title,
                'body' => ($customerName ? "مرحباً {$customerName}،\n\n" : "مرحباً،\n\n") . "\n{$tenantName}",
            ];
        }

        if ($locale === 'en') {
            return [
                'subject' => $trip->title,
                'body' => ($customerName ? "Hello {$customerName},\n\n" : "Hello,\n\n") . "\n{$tenantName}",
            ];
        }

        return [
            'subject' => $trip->title,
            'body' => ($customerName ? "שלום {$customerName},\n\n" : "שלום,\n\n") . "\n{$tenantName}",
        ];
    }

    private function formatDateRange(Trip $trip): string
    {
        if ($trip->start_date && $trip->end_date) {
            return $trip->start_date->format('d/m/Y') . ' - ' . $trip->end_date->format('d/m/Y');
        }

        return $trip->start_date?->format('d/m/Y') ?? $trip->end_date?->format('d/m/Y') ?? '';
    }

    private function currencySymbol(string $currency): string
    {
        return match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
    }
}
