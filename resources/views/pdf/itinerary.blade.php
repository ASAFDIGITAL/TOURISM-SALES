@php
    $lang = in_array($overrideLang ?? null, ['he', 'ar', 'en'], true)
        ? $overrideLang
        : ($tenant->language ?: 'he');
    $isRtl = $lang !== 'en';
    $isLightHeader = ($tenant->pdf_header_theme ?? 'dark') === 'light';
    $useOpenSans = $lang !== 'ar'
        && file_exists(storage_path('fonts/OpenSans-Regular.ttf'))
        && file_exists(storage_path('fonts/OpenSans-Bold.ttf'));
    $align = $isRtl ? 'right' : 'left';
    $oppAlign = $isRtl ? 'left' : 'right';

    $t = function (string $key) use ($lang): string {
        return __($key, [], $lang);
    };

    $f = function (?string $text) use ($isRtl): string {
        if ($text === null || $text === '') {
            return '';
        }

        return \App\Helpers\PdfHelper::fixForPdf($text, $isRtl);
    };

    $logoFullPath = null;
    if (!empty($tenant->logo_path)) {
        $publicCandidate = storage_path('app/public/' . $tenant->logo_path);
        $privateCandidate = storage_path('app/private/' . $tenant->logo_path);
        if (file_exists($publicCandidate)) {
            $logoFullPath = $publicCandidate;
        } elseif (file_exists($privateCandidate)) {
            $logoFullPath = $privateCandidate;
        }
    }

    $currency = $tenant->currency ?? 'ILS';
    $sym = match ($currency) {
        'ILS' => '₪',
        'EUR' => '€',
        'GBP' => '£',
        default => '$',
    };

    $total = (float) $trip->total_amount;
    $paid = (float) $trip->paid_amount;
    $balance = max(0, round($total - $paid, 2));

    $tripNights = null;
    if ($trip->start_date && $trip->end_date) {
        $diff = (int) $trip->start_date->diffInDays($trip->end_date, false);
        if ($diff > 0) {
            $tripNights = $diff;
        }
    }

    $passengers = is_array($trip->passengers) ? array_values(array_filter($trip->passengers, fn ($p) => filled($p['name'] ?? null))) : [];
    $passengersCount = count($passengers);
    $hotels = is_array($trip->hotels) ? array_values($trip->hotels) : [];
    $flights = is_array($trip->flights) ? array_values($trip->flights) : [];

    // Look up metadata (stars, type, address) from the agency's saved catalog if matching
    $hotelNames = collect($hotels)->pluck('name')->filter()->unique()->all();
    $savedProperties = collect();
    if (count($hotelNames) > 0 && $tenant?->id) {
        $savedProperties = \App\Models\Hotel::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('name', $hotelNames)
            ->get()
            ->keyBy('name');
    }

    // Resolve each stay's type (hotel vs apartment), address, and stars
    $resolvedStays = [];
    $hasHotelStay = false;
    $hasApartmentStay = false;
    foreach ($hotels as $hotel) {
        $name = trim((string) ($hotel['name'] ?? '-'));
        $saved = $savedProperties->get($name);
        $type = in_array($hotel['type'] ?? null, ['hotel', 'apartment'], true)
            ? $hotel['type']
            : ($saved?->type ?: 'hotel');

        if ($type === 'apartment') {
            $hasApartmentStay = true;
        } else {
            $hasHotelStay = true;
        }

        $address = filled($hotel['address'] ?? null)
            ? trim((string) $hotel['address'])
            : ($saved?->address ? trim((string) $saved->address) : null);

        $resolvedStays[] = [
            'name' => $name,
            'type' => $type,
            'address' => $address,
            'stars' => $saved?->stars ?? null,
            'from' => $hotel['from'] ?? null,
            'until' => $hotel['until'] ?? null,
            'rooms' => $hotel['rooms'] ?? null,
        ];
    }

    // Dynamic section heading: Hotel Stay / Apartment Stay / Hotel & Apartment Stay
    $stayHeadingKey = match (true) {
        $hasApartmentStay && $hasHotelStay => 'ui.hotel_and_apartment_stay_heading',
        $hasApartmentStay => 'ui.apartment_stay_heading',
        default => 'ui.hotel_stay_heading',
    };

    // Compact Vienna-style date range (e.g. 14-17.05.2027 or 28.05-03.06.2027)
    $dateRange = '-';
    if ($trip->start_date && $trip->end_date) {
        if ($trip->start_date->format('m.Y') === $trip->end_date->format('m.Y')) {
            $dateRange = $trip->start_date->format('d') . '-' . $trip->end_date->format('d.m.Y');
        } elseif ($trip->start_date->format('Y') === $trip->end_date->format('Y')) {
            $dateRange = $trip->start_date->format('d.m') . '-' . $trip->end_date->format('d.m.Y');
        } else {
            $dateRange = $trip->start_date->format('d.m.Y') . ' - ' . $trip->end_date->format('d.m.Y');
        }
    } elseif ($trip->start_date) {
        $dateRange = $trip->start_date->format('d.m.Y');
    } elseif ($trip->end_date) {
        $dateRange = $trip->end_date->format('d.m.Y');
    }

    $issueDateLabel = match ($lang) {
        'ar' => 'تاريخ الإصدار',
        'en' => 'Issued',
        default => 'הופק בתאריך',
    };
    $checkInLabel = match ($lang) {
        'ar' => 'دخول',
        'en' => 'Check-in',
        default => 'כניסה',
    };
    $checkOutLabel = match ($lang) {
        'ar' => 'مغادرة',
        'en' => 'Check-out',
        default => 'עזיבה',
    };

    $statusKey = 'ui.' . ($trip->status ?: 'draft');
    $statusBg = match ($trip->status) {
        'quote' => '#f3e8ff',
        'confirmed', 'completed' => '#e6f6ee',
        'cancelled' => '#fee2e2',
        default => '#fbf1dc',
    };
    $statusColor = match ($trip->status) {
        'quote' => '#6b21a8',
        'confirmed', 'completed' => '#065f46',
        'cancelled' => '#991b1b',
        default => '#976919',
    };

    // 4-column editorial facts strip (reversed in RTL so primary item is rightmost)
    $facts = [
        [
            'label' => $t('ui.prepared_for'),
            'value' => $customer?->name ? $f($customer->name) : '-',
            'is_ltr' => false,
        ],
        [
            'label' => $t('ui.travel_dates'),
            'value' => $dateRange,
            'is_ltr' => true,
        ],
        [
            'label' => $t('ui.nights'),
            'value' => $tripNights !== null ? (string) $tripNights : '-',
            'is_ltr' => true,
        ],
        [
            'label' => $t('ui.passengers'),
            'value' => $passengersCount > 0 ? (string) $passengersCount : '-',
            'is_ltr' => true,
        ],
    ];
    if ($isRtl) {
        $facts = array_reverse($facts);
    }

    $kpis = [
        [
            'label' => $t('ui.total_amount'),
            'value' => $sym . number_format($total, 2),
            'color' => '#102738',
        ],
        [
            'label' => $t('ui.paid_amount'),
            'value' => $sym . number_format($paid, 2),
            'color' => '#047857',
        ],
        [
            'label' => $t('ui.balance'),
            'value' => $sym . number_format($balance, 2),
            'color' => $balance > 0 ? '#976919' : '#047857',
        ],
    ];
    if ($isRtl) {
        $kpis = array_reverse($kpis);
    }

    // Crisp vector SVG icons as data URIs (DomPDF renders data:image/svg+xml cleanly with zero network requests)
    $svgUri = fn (string $svg): string => 'data:image/svg+xml;base64,' . base64_encode($svg);

    $iconPlane = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#976919" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21 3-6 7 2 9-2 2-4-8-4 4 .3 3-1.4 1.4L4 17l-3-2 1.5-1.4 3 .3 4-4-8-4 2-2 9 2 6-5c2-1 3 0 2.5 2z"/></svg>');
    $iconHotel = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#976919" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V4h14v17M10 21v-5h4v5M8 8h2m4 0h2m-8 4h2m4 0h2"/></svg>');
    $iconApartment = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#976919" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/></svg>');
    $iconUsers = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#976919" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>');
    $iconPayment = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#976919" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>');
    $iconBrandMark = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#bf841d" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L15 22l-4-9-9-4 20-7zM11 13l7-7"/></svg>');

    $stayIcon = ($hasApartmentStay && ! $hasHotelStay) ? $iconApartment : $iconHotel;

    // Subtle luxury architectural compass/horizon graphic for the Hero opposite side
    $heroAccentStroke = $isLightHeader ? '#d4b06a' : '#f6cc84';
    $heroAccentMuted = $isLightHeader ? '#e2e8f0' : '#1e425e';
    $heroArtwork = $svgUri('<svg xmlns="http://www.w3.org/2000/svg" width="220" height="130" viewBox="0 0 220 130"><circle cx="110" cy="65" r="54" fill="none" stroke="' . $heroAccentMuted . '" stroke-width="1.2"/><circle cx="110" cy="65" r="38" fill="none" stroke="' . $heroAccentMuted . '" stroke-width="1" stroke-dasharray="4 4"/><path d="M24 96 Q 100 22 196 38" fill="none" stroke="' . $heroAccentStroke . '" stroke-width="1.6" stroke-dasharray="5 4" opacity="0.85"/><circle cx="24" cy="96" r="3.5" fill="' . $heroAccentStroke . '"/><path d="M198 37 L186 33 L189 40 L185 45 Z" fill="' . $heroAccentStroke . '"/><path d="M110 20 L110 30 M110 100 L110 110 M65 65 L75 65 M145 65 L155 65" stroke="' . $heroAccentStroke . '" stroke-width="1.2" opacity="0.5"/></svg>');

    $contactLine = collect([$tenant->phone, $tenant->email])->filter()->implode('  ·  ');
    $aviationService = app(\App\Services\AviationDirectoryService::class);
    $localizedDestination = filled($trip->destination)
        ? $aviationService->localizeDestination($trip->destination, $lang)
        : '';
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $f($t('ui.trip_summary_label')) }} · {{ $f($trip->title) }}</title>
    <style>
        @if($useOpenSans)
        @font-face {
            font-family: 'OpenSans';
            src: url("{{ storage_path('fonts/OpenSans-Regular.ttf') }}") format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'OpenSans';
            src: url("{{ storage_path('fonts/OpenSans-Bold.ttf') }}") format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @endif
        @page {
            size: A4;
            margin: 10mm 13mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            @if($useOpenSans)
            font-family: 'OpenSans', 'DejaVu Sans', sans-serif;
            @else
            font-family: 'DejaVu Sans', sans-serif;
            @endif
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #192b3c;
            font-size: 11.5px;
            line-height: 1.45;
        }

        /* Editorial Top Bar (Agency Branded) */
        .top-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .top-bar td {
            vertical-align: middle;
        }
        .agency-logo {
            max-width: 120px;
            max-height: 34px;
            vertical-align: middle;
        }
        .agency-title {
            font-size: 13px;
            font-weight: bold;
            color: #192b3c;
        }
        .agency-slash {
            font-size: 11px;
            color: #596777;
        }
        .agency-sub {
            font-size: 9.5px;
            color: #596777;
            margin-top: 2px;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 14px;
            font-size: 9.5px;
            font-weight: bold;
            background: {{ $statusBg }};
            color: {{ $statusColor }};
        }

        /* Editorial Hero Card (Vienna Midnight Navy + Warm Gold) */
        .hero {
            background: {{ $isLightHeader ? '#f7f6f2' : '#102738' }};
            color: {{ $isLightHeader ? '#102738' : '#ffffff' }};
            border-radius: 10px;
            padding: 15px 22px;
            margin-bottom: 2px;
            page-break-inside: avoid;
            @if($isLightHeader)
            border: 1px solid #e2ddd0;
            border-bottom: 3px solid #c29340;
            @else
            border-bottom: 3px solid #c29340;
            @endif
        }
        .hero-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hero-table td {
            vertical-align: middle;
        }
        .hero-kicker {
            color: {{ $isLightHeader ? '#976919' : '#f6cc84' }};
            font-size: 10.5px;
            font-weight: bold;
            margin-bottom: 4px;
            text-align: {{ $align }};
        }
        .hero-title {
            font-size: 25px;
            font-weight: bold;
            line-height: 1.18;
            margin: 0 0 5px 0;
            color: {{ $isLightHeader ? '#102738' : '#ffffff' }};
            text-align: {{ $align }};
        }
        .hero-subtitle {
            font-size: 12px;
            color: {{ $isLightHeader ? '#526372' : '#e5ebee' }};
            margin: 0;
            text-align: {{ $align }};
        }

        /* 4-Column Editorial Facts Strip */
        .facts-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid #cad3d9;
            margin-bottom: 11px;
            page-break-inside: avoid;
        }
        .facts-table td {
            width: 25%;
            padding: 9px 4px 8px 4px;
            vertical-align: top;
            text-align: {{ $align }};
        }
        .fact-label {
            display: block;
            font-size: 9.5px;
            color: #586978;
            margin-bottom: 2px;
        }
        .fact-value {
            display: block;
            font-size: 12.5px;
            font-weight: bold;
            color: #192b3c;
        }

        /* Section Header with Gold Circular Icon + Divider Rule */
        .section-head {
            width: 100%;
            border-collapse: collapse;
            margin: 11px 0 7px 0;
            page-break-after: avoid;
        }
        .section-head td {
            vertical-align: middle;
        }
        .section-icon-cell {
            width: 30px;
        }
        .section-icon-circle {
            width: 26px;
            height: 26px;
            border-radius: 13px;
            background: #fbf1dc;
            text-align: center;
            line-height: 26px;
        }
        .section-icon-circle img {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-top: 6px;
        }
        .section-title-cell {
            white-space: nowrap;
            padding: 0 8px;
        }
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #192b3c;
            margin: 0;
        }
        .section-rule-cell {
            width: 100%;
        }
        .section-rule {
            border-top: 1px solid #d6dde0;
            height: 1px;
            width: 100%;
        }

        /* Editorial Flights Table */
        .flights-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .flights-table th {
            background: #eff3f4;
            color: #4b606c;
            font-size: 10px;
            font-weight: bold;
            padding: 6px 8px;
            text-align: {{ $align }};
        }
        .flights-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #dce2e4;
            font-size: 11px;
            color: #192b3c;
            text-align: {{ $align }};
            vertical-align: middle;
        }
        .flights-table tr {
            page-break-inside: avoid;
        }
        .flight-sub {
            display: block;
            font-size: 9px;
            color: #586978;
            margin-top: 1px;
        }

        /* Warm Sand Hotel / Apartment Callout Block */
        .hotel-card {
            background: #f7f6f2;
            padding: 8px 13px;
            margin-bottom: 6px;
            page-break-inside: avoid;
            @if($isRtl)
            border-right: 3px solid #c29340;
            @else
            border-left: 3px solid #c29340;
            @endif
            text-align: {{ $align }};
        }
        .stay-type-badge {
            display: inline-block;
            background: #fbf1dc;
            color: #976919;
            font-size: 9px;
            font-weight: bold;
            padding: 1px 7px;
            border-radius: 8px;
            vertical-align: middle;
        }
        .hotel-name {
            font-size: 13.5px;
            font-weight: bold;
            color: #192b3c;
            vertical-align: middle;
        }
        .hotel-stars {
            color: #bf841d;
            font-size: 11px;
            vertical-align: middle;
        }
        .hotel-address {
            font-size: 10.5px;
            color: #334155;
            margin-top: 3px;
        }
        .hotel-meta {
            font-size: 10.5px;
            color: #4b5d6c;
            margin-top: 2px;
        }

        /* Passengers Table */
        .pax-list {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .pax-list td {
            padding: 4px 4px;
            border-bottom: 1px solid #e2e7e8;
            font-size: 11px;
            color: #192b3c;
            text-align: {{ $align }};
            vertical-align: middle;
        }
        .pax-idx {
            color: #966712;
            font-weight: bold;
            font-size: 11px;
            width: 26px;
        }
        .pax-passport {
            font-size: 10px;
            color: #586978;
            white-space: nowrap;
        }

        /* Compact Editorial Payment Summary */
        .pay-strip {
            width: 100%;
            border-collapse: collapse;
            background: #f7f6f2;
            border-top: 1px solid #e5e0d3;
            border-bottom: 1px solid #e5e0d3;
            margin-top: 3px;
            page-break-inside: avoid;
        }
        .pay-strip td {
            width: 33.33%;
            padding: 8px 12px;
            text-align: {{ $align }};
            vertical-align: top;
        }
        .pay-label {
            display: block;
            font-size: 9.5px;
            color: #586978;
            margin-bottom: 2px;
        }
        .pay-val {
            display: block;
            font-size: 13.5px;
            font-weight: bold;
        }

        /* Gold-Top Editorial Footer with System Credit */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 2px solid #b48b46;
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .footer-table td {
            padding-top: 7px;
            font-size: 9.5px;
            color: #526372;
            vertical-align: middle;
        }
        .credit-brand {
            font-weight: bold;
            color: #192b3c;
            letter-spacing: 0.3px;
        }
        .credit-icon {
            width: 12px;
            height: 12px;
            vertical-align: middle;
            margin-right: 4px;
            margin-bottom: -2px;
        }
        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>

    {{-- Top Agency Header Bar --}}
    <table class="top-bar">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 40%;">
                    <span class="status-pill">{{ $f($t($statusKey)) }}</span>
                    @if($contactLine !== '')
                        <div class="agency-sub" style="margin-top: 4px;">{{ $contactLine }}</div>
                    @endif
                </td>
                <td style="text-align: right; width: 60%;">
                    @if($logoFullPath)
                        <div style="margin-bottom: 3px;">
                            <img src="{{ $logoFullPath }}" class="agency-logo" alt="">
                        </div>
                    @endif
                    <div>
                        <span class="agency-slash">{{ $f($t('ui.trip_summary_label')) }} / </span>
                        <span class="agency-title">{{ $f($tenant->name) }}</span>
                    </div>
                </td>
            @else
                <td style="text-align: left; width: 60%;">
                    @if($logoFullPath)
                        <div style="margin-bottom: 3px;">
                            <img src="{{ $logoFullPath }}" class="agency-logo" alt="">
                        </div>
                    @endif
                    <div>
                        <span class="agency-title">{{ $f($tenant->name) }}</span>
                        <span class="agency-slash"> / {{ $t('ui.trip_summary_label') }}</span>
                    </div>
                </td>
                <td style="text-align: right; width: 40%;">
                    <span class="status-pill">{{ $t($statusKey) }}</span>
                    @if($contactLine !== '')
                        <div class="agency-sub" style="margin-top: 4px;">{{ $contactLine }}</div>
                    @endif
                </td>
            @endif
        </tr>
    </table>

    {{-- Editorial Hero Banner --}}
    <div class="hero">
        <table class="hero-table">
            <tr>
                @if($isRtl)
                    <td style="width: 36%; text-align: left;">
                        <img src="{{ $heroArtwork }}" style="width: 170px; height: auto;" alt="">
                    </td>
                    <td style="width: 64%; text-align: right;">
                        <div class="hero-kicker">{{ $f($t('ui.itinerary')) }}</div>
                        <div class="hero-title">{{ $f($trip->title) }}</div>
                        @if($localizedDestination !== '')
                            <div class="hero-subtitle">{{ $f($localizedDestination) }}</div>
                        @endif
                    </td>
                @else
                    <td style="width: 64%; text-align: left;">
                        <div class="hero-kicker">{{ $t('ui.itinerary') }}</div>
                        <div class="hero-title">{{ $f($trip->title) }}</div>
                        @if($localizedDestination !== '')
                            <div class="hero-subtitle">{{ $f($localizedDestination) }}</div>
                        @endif
                    </td>
                    <td style="width: 36%; text-align: right;">
                        <img src="{{ $heroArtwork }}" style="width: 170px; height: auto;" alt="">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    {{-- 4-Column Editorial Facts Strip --}}
    <table class="facts-table">
        <tr>
            @foreach($facts as $fact)
                <td>
                    <span class="fact-label">{{ $f($fact['label']) }}</span>
                    <span class="fact-value {{ $fact['is_ltr'] ? 'nowrap' : '' }}">{{ $fact['value'] }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Flights Section --}}
    @if(count($flights) > 0)
        <table class="section-head">
            <tr>
                @if($isRtl)
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $f($t('ui.flight_details_heading')) }}</div></td>
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconPlane }}" alt=""></div></td>
                @else
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconPlane }}" alt=""></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $t('ui.flight_details_heading') }}</div></td>
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                @endif
            </tr>
        </table>

        <table class="flights-table">
            <thead>
                <tr>
                    @if($isRtl)
                        <th style="width: 24%;">{{ $f($t('ui.local_departure')) }}</th>
                        <th style="width: 22%;">{{ $f($t('ui.flight_number')) }}</th>
                        <th style="width: 39%;">{{ $f($t('ui.flight_route')) }}</th>
                        <th style="width: 15%;">{{ $f($t('ui.flight_direction')) }}</th>
                    @else
                        <th style="width: 15%;">{{ $t('ui.flight_direction') }}</th>
                        <th style="width: 39%;">{{ $t('ui.flight_route') }}</th>
                        <th style="width: 22%;">{{ $t('ui.flight_number') }}</th>
                        <th style="width: 24%;">{{ $t('ui.local_departure') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($flights as $flight)
                    @php
                        $flightType = $flight['type'] ?? 'outbound';
                        $depFormatted = !empty($flight['departure_time'])
                            ? \Carbon\Carbon::parse($flight['departure_time'])->format('d.m · H:i')
                            : '-';
                        $arrFormatted = !empty($flight['arrival_time'])
                            ? \Carbon\Carbon::parse($flight['arrival_time'])->format('d.m · H:i')
                            : null;
                        $fromCode = $aviationService->localizeAirport((string) ($flight['from_airport'] ?? ''), $lang);
                        $toCode = $aviationService->localizeAirport((string) ($flight['to_airport'] ?? ''), $lang);
                        $airlineDisplay = trim((string) ($flight['airline'] ?? ''));
                        $flightNoDisplay = trim((string) ($flight['flight_number'] ?? ''));
                        $routeDisplay = ($fromCode || $toCode)
                            ? trim($f($fromCode) . ' → ' . $f($toCode), ' →')
                            : ($airlineDisplay !== '' ? $f($airlineDisplay) : '-');
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td class="nowrap">
                                {{ $depFormatted }}
                                @if($arrFormatted)
                                    <span class="flight-sub">→ {{ $arrFormatted }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="nowrap" style="font-weight: bold;">{{ $flightNoDisplay !== '' ? $flightNoDisplay : '-' }}</span>
                                @if($airlineDisplay !== '' && ($fromCode || $toCode))
                                    <span class="flight-sub">{{ $f($airlineDisplay) }}</span>
                                @endif
                            </td>
                            <td>{{ $routeDisplay }}</td>
                            <td style="font-weight: bold;">{{ $f($t("ui.{$flightType}")) }}</td>
                        @else
                            <td style="font-weight: bold;">{{ $t("ui.{$flightType}") }}</td>
                            <td>{{ $routeDisplay }}</td>
                            <td>
                                <span class="nowrap" style="font-weight: bold;">{{ $flightNoDisplay !== '' ? $flightNoDisplay : '-' }}</span>
                                @if($airlineDisplay !== '' && ($fromCode || $toCode))
                                    <span class="flight-sub">{{ $f($airlineDisplay) }}</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                {{ $depFormatted }}
                                @if($arrFormatted)
                                    <span class="flight-sub">→ {{ $arrFormatted }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Hotels / Apartments Section --}}
    @if(count($resolvedStays) > 0)
        <table class="section-head">
            <tr>
                @if($isRtl)
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $f($t($stayHeadingKey)) }}</div></td>
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $stayIcon }}" alt=""></div></td>
                @else
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $stayIcon }}" alt=""></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $t($stayHeadingKey) }}</div></td>
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                @endif
            </tr>
        </table>

        @foreach($resolvedStays as $stay)
            @php
                $stayName = $stay['name'];
                $stayTypeLabel = $stay['type'] === 'apartment'
                    ? $t('ui.accommodation_type_apartment')
                    : $t('ui.accommodation_type_hotel');
                $stars = $stay['stars'];
                $stayAddress = $stay['address'];
                $checkIn = !empty($stay['from']) ? \Carbon\Carbon::parse($stay['from'])->format('d.m.Y') : null;
                $checkOut = !empty($stay['until']) ? \Carbon\Carbon::parse($stay['until'])->format('d.m.Y') : null;
                $hNights = null;
                if (!empty($stay['from']) && !empty($stay['until'])) {
                    $d = (int) \Carbon\Carbon::parse($stay['from'])->diffInDays(\Carbon\Carbon::parse($stay['until']), false);
                    if ($d > 0) {
                        $hNights = $d;
                    }
                }
                $rooms = !empty($stay['rooms']) ? $stay['rooms'] : null;

                $hParts = [];
                if ($checkIn) {
                    $hParts[] = $isRtl ? ($checkIn . ' ' . $f($checkInLabel)) : ($checkInLabel . ' ' . $checkIn);
                }
                if ($checkOut) {
                    $hParts[] = $isRtl ? ($checkOut . ' ' . $f($checkOutLabel)) : ($checkOutLabel . ' ' . $checkOut);
                }
                if ($hNights !== null) {
                    $hParts[] = $isRtl ? ($f($t('ui.nights')) . ' ' . $hNights) : ($hNights . ' ' . $t('ui.nights'));
                }
                if ($rooms) {
                    $hParts[] = $isRtl ? ($f($t('ui.rooms')) . ' ' . $rooms) : ($rooms . ' ' . $t('ui.rooms'));
                }
                if ($isRtl) {
                    $hParts = array_reverse($hParts);
                }
            @endphp
            <div class="hotel-card">
                <div>
                    @if($isRtl)
                        @if($stars)
                            <span class="hotel-stars">{{ str_repeat('★', (int) $stars) }}</span>&nbsp;
                        @endif
                        <span class="hotel-name">{{ $f($stayName) }}</span>
                        &nbsp;<span class="stay-type-badge">{{ $f($stayTypeLabel) }}</span>
                    @else
                        <span class="stay-type-badge">{{ $stayTypeLabel }}</span>&nbsp;
                        <span class="hotel-name">{{ $f($stayName) }}</span>
                        @if($stars)
                            &nbsp;<span class="hotel-stars">{{ str_repeat('★', (int) $stars) }}</span>
                        @endif
                    @endif
                </div>
                @if(filled($stayAddress))
                    <div class="hotel-address">
                        @if($isRtl)
                            {{ $f($stayAddress) }} :{{ $f($t('ui.address')) }}
                        @else
                            <strong>{{ $t('ui.address') }}:</strong> {{ $f($stayAddress) }}
                        @endif
                    </div>
                @endif
                @if(count($hParts) > 0)
                    <div class="hotel-meta">{{ implode('   ·   ', $hParts) }}</div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Passengers Section --}}
    @if($passengersCount > 0)
        <table class="section-head">
            <tr>
                @if($isRtl)
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $f($t('ui.passengers')) }}</div></td>
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconUsers }}" alt=""></div></td>
                @else
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconUsers }}" alt=""></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $t('ui.passengers') }}</div></td>
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                @endif
            </tr>
        </table>

        <table class="pax-list">
            <tbody>
                @foreach($passengers as $idx => $passenger)
                    @php
                        $paxPassport = trim((string) ($passenger['passport_number'] ?? ''));
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td class="pax-passport" style="text-align: left;">
                                @if($paxPassport !== '')
                                    {{ $paxPassport }} :{{ $f($t('ui.passport_number')) }}
                                @endif
                            </td>
                            <td>{{ $f($passenger['name'] ?? '') }}</td>
                            <td class="pax-idx" style="text-align: right;">{{ sprintf('%02d', $idx + 1) }}</td>
                        @else
                            <td class="pax-idx" style="text-align: left;">{{ sprintf('%02d', $idx + 1) }}</td>
                            <td>{{ $f($passenger['name'] ?? '') }}</td>
                            <td class="pax-passport" style="text-align: right;">
                                @if($paxPassport !== '')
                                    {{ $t('ui.passport_number') }}: {{ $paxPassport }}
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Payment Summary Section (Client-Facing Only — Never Shows Supplier Cost) --}}
    @if($total > 0)
        <table class="section-head">
            <tr>
                @if($isRtl)
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $f($t('ui.portal_payment_summary')) }}</div></td>
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconPayment }}" alt=""></div></td>
                @else
                    <td class="section-icon-cell"><div class="section-icon-circle"><img src="{{ $iconPayment }}" alt=""></div></td>
                    <td class="section-title-cell"><div class="section-title">{{ $t('ui.portal_payment_summary') }}</div></td>
                    <td class="section-rule-cell"><div class="section-rule"></div></td>
                @endif
            </tr>
        </table>

        <table class="pay-strip">
            <tr>
                @foreach($kpis as $kpi)
                    <td>
                        <span class="pay-label">{{ $f($kpi['label']) }}</span>
                        <span class="pay-val nowrap" style="color: {{ $kpi['color'] }};">{{ $kpi['value'] }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    {{-- Gold-Bordered Editorial Footer with Agency Info + System Credit --}}
    <table class="footer-table">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 45%;" class="nowrap">
                    <img src="{{ $iconBrandMark }}" class="credit-icon" alt="">
                    <span class="credit-brand">TOURISM-SALES</span>
                    <span> / by ASAF DIGITAL</span>
                </td>
                <td style="text-align: right; width: 55%;">
                    {{ now()->format('d.m.Y') }} {{ $f($issueDateLabel) }}
                    @if($tenant->name)
                        &nbsp;·&nbsp; {{ $f($tenant->name) }}
                    @endif
                </td>
            @else
                <td style="text-align: left; width: 55%;">
                    @if($tenant->name)
                        {{ $f($tenant->name) }} &nbsp;·&nbsp;
                    @endif
                    {{ $issueDateLabel }} {{ now()->format('d.m.Y') }}
                </td>
                <td style="text-align: right; width: 45%;" class="nowrap">
                    <img src="{{ $iconBrandMark }}" class="credit-icon" alt="">
                    <span class="credit-brand">TOURISM-SALES</span>
                    <span> / by ASAF DIGITAL</span>
                </td>
            @endif
        </tr>
    </table>

</body>
</html>
