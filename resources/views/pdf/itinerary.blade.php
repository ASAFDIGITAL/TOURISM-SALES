@php
    $lang = $tenant->language ?: 'he';
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

        return $isRtl ? \App\Helpers\PdfHelper::fixRtl($text) : $text;
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

    $passengers = is_array($trip->passengers) ? array_values($trip->passengers) : [];
    $passengersCount = count($passengers);
    $hotels = is_array($trip->hotels) ? array_values($trip->hotels) : [];
    $flights = is_array($trip->flights) ? array_values($trip->flights) : [];

    $dateRange = '';
    if ($trip->start_date && $trip->end_date) {
        $dateRange = $trip->start_date->format('d/m/Y') . ' - ' . $trip->end_date->format('d/m/Y');
    } elseif ($trip->start_date) {
        $dateRange = $trip->start_date->format('d/m/Y');
    } elseif ($trip->end_date) {
        $dateRange = $trip->end_date->format('d/m/Y');
    }

    $issueDateLabel = match ($lang) {
        'ar' => 'تاريخ الإصدار',
        'en' => 'Issue Date',
        default => 'תאריך הפקה',
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
        'quote' => '#581c87',
        'confirmed', 'completed' => '#065f46',
        default => '#78350f',
    };
    $statusColor = match ($trip->status) {
        'quote' => '#f3e8ff',
        'confirmed', 'completed' => '#d1fae5',
        default => '#fef3c7',
    };

    $kpis = [
        [
            'label' => $t('ui.total_amount'),
            'value' => $sym . number_format($total, 2),
            'color' => '#0f172a',
        ],
        [
            'label' => $t('ui.paid_amount'),
            'value' => $sym . number_format($paid, 2),
            'color' => '#059669',
        ],
        [
            'label' => $t('ui.balance'),
            'value' => $sym . number_format($balance, 2),
            'color' => $balance > 0 ? '#d97706' : '#059669',
        ],
    ];
    if ($isRtl) {
        $kpis = array_reverse($kpis);
    }
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $f($t('ui.itinerary')) }} - {{ $f($trip->title) }}</title>
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
            margin: 10mm;
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
            color: #0f172a;
            font-size: 11.5px;
            line-height: 1.55;
            background: #f1f5f9;
        }
        .page-wrap {
            background: #f1f5f9;
            padding: 0;
        }

        /* Luxury Portal Hero Banner */
        .hero-box {
            background: {{ $isLightHeader ? '#ffffff' : '#0f172a' }};
            color: {{ $isLightHeader ? '#0f172a' : '#ffffff' }};
            border-bottom: 4px solid #f59e0b;
            border-radius: 14px;
            padding: 20px 22px 18px 22px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            @if($isLightHeader)
            border: 1px solid #e2e8f0;
            border-bottom: 4px solid #f59e0b;
            @endif
        }
        .hero-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .hero-top td {
            vertical-align: middle;
        }
        .agency-logo {
            max-width: 130px;
            max-height: 42px;
            vertical-align: middle;
        }
        .agency-name {
            font-size: 13px;
            font-weight: bold;
            color: {{ $isLightHeader ? '#0f172a' : '#ffffff' }};
        }
        .agency-contact {
            font-size: 9.5px;
            color: {{ $isLightHeader ? '#64748b' : '#94a3b8' }};
            margin-top: 2px;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 10px;
            font-weight: bold;
            background: {{ $statusBg }};
            color: {{ $statusColor }};
        }
        .hero-kind {
            font-size: 10px;
            font-weight: bold;
            color: #f59e0b;
            text-align: {{ $align }};
            margin-bottom: 3px;
        }
        .hero-title {
            font-size: 22px;
            font-weight: bold;
            line-height: 1.25;
            margin: 0 0 8px 0;
            text-align: {{ $align }};
            color: {{ $isLightHeader ? '#0f172a' : '#ffffff' }};
        }
        .hero-meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .hero-meta td {
            font-size: 10.5px;
            color: {{ $isLightHeader ? '#475569' : '#cbd5e1' }};
            text-align: {{ $align }};
            padding-top: 2px;
        }

        /* Portal Cards */
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px 18px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .card-allow-break {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px 18px;
            margin-bottom: 12px;
        }
        .card-heading {
            font-size: 13.5px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 11px 0;
            padding-bottom: 7px;
            border-bottom: 1px solid #f1f5f9;
            text-align: {{ $align }};
            page-break-after: avoid;
        }
        .count-pill {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            font-size: 10px;
            font-weight: bold;
            padding: 1px 8px;
            border-radius: 10px;
            vertical-align: middle;
        }

        /* Payment KPIs */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px;
        }
        .kpi-cell {
            width: 33.33%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: {{ $align }};
            vertical-align: top;
        }
        .kpi-label {
            font-size: 9.5px;
            color: #64748b;
            font-weight: bold;
            display: block;
        }
        .kpi-value {
            font-size: 15px;
            font-weight: bold;
            display: block;
            margin-top: 3px;
        }

        /* Item Boxes (Flights & Hotels) */
        .item-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 13px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .item-box:last-child {
            margin-bottom: 0;
        }
        .item-row {
            width: 100%;
            border-collapse: collapse;
        }
        .item-row td {
            vertical-align: middle;
        }
        .item-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .item-route {
            font-size: 11px;
            font-weight: bold;
            color: #d97706;
        }
        .item-sub {
            font-size: 10.5px;
            color: #475569;
            margin-top: 4px;
            text-align: {{ $align }};
        }
        .type-tag {
            display: inline-block;
            background: #0f172a;
            color: #fbbf24;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 6px;
        }

        /* Passengers */
        .pax-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pax-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11.5px;
            text-align: {{ $align }};
        }
        .pax-table tr:last-child td {
            border-bottom: none;
        }
        .pax-num {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            font-size: 10px;
            font-weight: bold;
            padding: 1px 7px;
            border-radius: 8px;
        }

        /* Summary Box */
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 11px 14px;
            text-align: {{ $align }};
            font-size: 11.5px;
            line-height: 1.65;
            color: #334155;
        }
        .summary-box p {
            margin: 0 0 6px 0;
        }
        .summary-box p:last-child {
            margin-bottom: 0;
        }

        /* Footer */
        .foot {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #cbd5e1;
            font-size: 9.5px;
            color: #64748b;
            page-break-inside: avoid;
        }
        .foot td {
            padding: 8px 4px 0 4px;
        }
        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="page-wrap">

    {{-- Luxury Portal-Style Hero Header --}}
    <div class="hero-box">
        <table class="hero-top">
            <tr>
                @if($isRtl)
                    <td style="text-align: left; width: 35%;">
                        <span class="status-pill">{{ $f($t($statusKey)) }}</span>
                    </td>
                    <td style="text-align: right; width: 65%;">
                        @if($logoFullPath)
                            <div style="margin-bottom: 5px;">
                                <img src="{{ $logoFullPath }}" class="agency-logo" alt="">
                            </div>
                        @endif
                        <div class="agency-name">{{ $f($tenant->name) }}</div>
                        @if($tenant->phone || $tenant->email)
                            <div class="agency-contact">
                                {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}
                            </div>
                        @endif
                    </td>
                @else
                    <td style="text-align: left; width: 65%;">
                        @if($logoFullPath)
                            <div style="margin-bottom: 5px;">
                                <img src="{{ $logoFullPath }}" class="agency-logo" alt="">
                            </div>
                        @endif
                        <div class="agency-name">{{ $tenant->name }}</div>
                        @if($tenant->phone || $tenant->email)
                            <div class="agency-contact">
                                {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}
                            </div>
                        @endif
                    </td>
                    <td style="text-align: right; width: 35%;">
                        <span class="status-pill">{{ $t($statusKey) }}</span>
                    </td>
                @endif
            </tr>
        </table>

        <div class="hero-kind">{{ $f($t('ui.itinerary')) }}</div>
        <div class="hero-title">{{ $f($trip->title) }}</div>

        <table class="hero-meta">
            <tr>
                <td>
                    @php
                        $metaParts = [];
                        if ($customer?->name) {
                            $metaParts[] = $f($customer->name);
                        }
                        if ($trip->destination) {
                            $metaParts[] = $f($trip->destination);
                        }
                        if ($dateRange !== '') {
                            $metaParts[] = $dateRange;
                        }
                        if ($tripNights !== null) {
                            $metaParts[] = $tripNights . ' ' . $f($t('ui.nights'));
                        }
                    @endphp
                    {{ implode('   |   ', $metaParts) }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Payment Summary Card --}}
    @if($total > 0)
    <div class="card">
        <div class="card-heading">{{ $f($t('ui.portal_payment_summary')) }}</div>
        <table class="kpi-table">
            <tr>
                @foreach($kpis as $kpi)
                    <td class="kpi-cell">
                        <span class="kpi-label">{{ $f($kpi['label']) }}</span>
                        <span class="kpi-value" style="color: {{ $kpi['color'] }};">{{ $kpi['value'] }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    {{-- Flights Card --}}
    @if(count($flights) > 0)
    <div class="card-allow-break">
        <div class="card-heading">
            @if($isRtl)
                <span class="count-pill">{{ count($flights) }}</span> {{ $f($t('ui.flights')) }}
            @else
                {{ $t('ui.flights') }} <span class="count-pill">{{ count($flights) }}</span>
            @endif
        </div>

        @foreach($flights as $flight)
            @php
                $flightType = $flight['type'] ?? 'outbound';
                $depFormatted = !empty($flight['departure_time'])
                    ? \Carbon\Carbon::parse($flight['departure_time'])->format('d/m/Y H:i')
                    : null;
                $arrFormatted = !empty($flight['arrival_time'])
                    ? \Carbon\Carbon::parse($flight['arrival_time'])->format('d/m/Y H:i')
                    : null;
                $fromCode = trim((string) ($flight['from_airport'] ?? ''));
                $toCode = trim((string) ($flight['to_airport'] ?? ''));
                $routeDisplay = ($fromCode || $toCode) ? trim($fromCode . ' → ' . $toCode, ' →') : '';
                $airlineDisplay = trim((string) ($flight['airline'] ?? ''));
                $flightNoDisplay = trim((string) ($flight['flight_number'] ?? ''));
            @endphp
            <div class="item-box">
                <table class="item-row">
                    <tr>
                        @if($isRtl)
                            <td style="text-align: left; width: 42%;">
                                @if($routeDisplay !== '')
                                    <span class="item-route">{{ $routeDisplay }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; width: 58%;">
                                <span class="type-tag">{{ $f($t("ui.{$flightType}")) }}</span>
                                &nbsp;
                                <span class="item-title">
                                    {{ $flightNoDisplay }} {{ $airlineDisplay !== '' ? $f($airlineDisplay) : '' }}
                                </span>
                            </td>
                        @else
                            <td style="text-align: left; width: 58%;">
                                <span class="type-tag">{{ $t("ui.{$flightType}") }}</span>
                                &nbsp;
                                <span class="item-title">
                                    {{ $airlineDisplay }} {{ $flightNoDisplay }}
                                </span>
                            </td>
                            <td style="text-align: right; width: 42%;">
                                @if($routeDisplay !== '')
                                    <span class="item-route">{{ $routeDisplay }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                </table>
                @if($depFormatted || $arrFormatted)
                    <div class="item-sub">
                        @if($depFormatted)
                            <span class="nowrap">{{ $depFormatted }}</span>
                        @endif
                        @if($depFormatted && $arrFormatted)
                            &nbsp;→&nbsp;
                        @endif
                        @if($arrFormatted)
                            <span class="nowrap">{{ $arrFormatted }}</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    {{-- Hotels Card --}}
    @if(count($hotels) > 0)
    <div class="card-allow-break">
        <div class="card-heading">
            @if($isRtl)
                <span class="count-pill">{{ count($hotels) }}</span> {{ $f($t('ui.hotels')) }}
            @else
                {{ $t('ui.hotels') }} <span class="count-pill">{{ count($hotels) }}</span>
            @endif
        </div>

        @foreach($hotels as $hotel)
            @php
                $checkIn = !empty($hotel['from']) ? \Carbon\Carbon::parse($hotel['from'])->format('d/m/Y') : null;
                $checkOut = !empty($hotel['until']) ? \Carbon\Carbon::parse($hotel['until'])->format('d/m/Y') : null;
                $hNights = null;
                if (!empty($hotel['from']) && !empty($hotel['until'])) {
                    $d = (int) \Carbon\Carbon::parse($hotel['from'])->diffInDays(\Carbon\Carbon::parse($hotel['until']), false);
                    if ($d > 0) {
                        $hNights = $d;
                    }
                }
                $rooms = !empty($hotel['rooms']) ? $hotel['rooms'] : null;
            @endphp
            <div class="item-box">
                <table class="item-row">
                    <tr>
                        @if($isRtl)
                            <td style="text-align: left; width: 35%;">
                                @if($hNights !== null)
                                    <span class="count-pill">{{ $hNights }} {{ $f($t('ui.nights')) }}</span>
                                @endif
                            </td>
                            <td style="text-align: right; width: 65%;">
                                <span class="item-title">{{ $f($hotel['name'] ?? '-') }}</span>
                            </td>
                        @else
                            <td style="text-align: left; width: 65%;">
                                <span class="item-title">{{ $hotel['name'] ?? '-' }}</span>
                            </td>
                            <td style="text-align: right; width: 35%;">
                                @if($hNights !== null)
                                    <span class="count-pill">{{ $hNights }} {{ $t('ui.nights') }}</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                </table>
                <div class="item-sub">
                    @php
                        $hDetails = [];
                        if ($checkIn) {
                            $hDetails[] = $f($checkInLabel) . ': ' . $checkIn;
                        }
                        if ($checkOut) {
                            $hDetails[] = $f($checkOutLabel) . ': ' . $checkOut;
                        }
                        if ($rooms) {
                            $hDetails[] = $rooms . ' ' . $f($t('ui.rooms'));
                        }
                    @endphp
                    {{ implode('   |   ', $hDetails) }}
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Passengers & Summary Card --}}
    @if($passengersCount > 0 || !empty($trip->trip_summary))
    <div class="card-allow-break">
        <div class="card-heading">
            @if($isRtl)
                @if($passengersCount > 0)
                    <span class="count-pill">{{ $passengersCount }}</span>
                @endif
                {{ $f($t('ui.portal_trip_details')) }}
            @else
                {{ $t('ui.portal_trip_details') }}
                @if($passengersCount > 0)
                    <span class="count-pill">{{ $passengersCount }}</span>
                @endif
            @endif
        </div>

        @if($passengersCount > 0)
            <table class="pax-table" style="{{ !empty($trip->trip_summary) ? 'margin-bottom: 12px;' : '' }}">
                <tbody>
                    @foreach($passengers as $idx => $passenger)
                        <tr>
                            <td>
                                @if($isRtl)
                                    {{ $f($passenger['name'] ?? '') }} &nbsp;<span class="pax-num">{{ $idx + 1 }}</span>
                                @else
                                    <span class="pax-num">{{ $idx + 1 }}</span>&nbsp; {{ $passenger['name'] ?? '' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(!empty($trip->trip_summary))
            <div class="summary-box">
                {!! $isRtl ? \App\Helpers\PdfHelper::fixRtl($trip->trip_summary) : $trip->trip_summary !!}
            </div>
        @endif
    </div>
    @endif

    {{-- Footer --}}
    <table class="foot">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 35%;">
                    {{ now()->format('d/m/Y') }} {{ $f($issueDateLabel) }}
                </td>
                <td style="text-align: right; width: 65%;">
                    {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}@if(($tenant->email || $tenant->phone) && $tenant->name) · @endif{{ $f($tenant->name) }}
                </td>
            @else
                <td style="text-align: left; width: 65%;">
                    {{ $tenant->name }}@if($tenant->name && $tenant->phone) · @endif{{ $tenant->phone }}@if(($tenant->name || $tenant->phone) && $tenant->email) · @endif{{ $tenant->email }}
                </td>
                <td style="text-align: right; width: 35%;">
                    {{ $issueDateLabel }} {{ now()->format('d/m/Y') }}
                </td>
            @endif
        </tr>
    </table>

</div>
</body>
</html>
