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
        $dateRange = $trip->start_date->format('d/m/Y') . ' – ' . $trip->end_date->format('d/m/Y');
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
    $tripDatesLabel = match ($lang) {
        'ar' => 'تواريخ الرحلة',
        'en' => 'Trip Dates',
        default => 'תאריכי הטיול',
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
    $roomsLabel = match ($lang) {
        'ar' => 'غرف',
        'en' => 'Rooms',
        default => 'חדרים',
    };

    $facts = [];
    if ($customer) {
        $facts[] = [
            'label' => $t('ui.customer'),
            'value' => $customer->name,
            'sub' => $customer->phone ?: ($customer->email ?: null),
            'is_text' => true,
        ];
    }
    if ($dateRange !== '') {
        $facts[] = [
            'label' => $tripDatesLabel,
            'value' => $dateRange,
            'sub' => null,
            'is_text' => false,
            'nowrap' => true,
        ];
    }
    if ($tripNights !== null) {
        $facts[] = [
            'label' => $t('ui.nights'),
            'value' => (string) $tripNights,
            'sub' => null,
            'is_text' => false,
        ];
    }
    if ($passengersCount > 0) {
        $facts[] = [
            'label' => $t('ui.passengers'),
            'value' => (string) $passengersCount,
            'sub' => null,
            'is_text' => false,
        ];
    }
    if ($isRtl) {
        $facts = array_reverse($facts);
    }
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $f($t('ui.itinerary')) }} · {{ $f($trip->title) }}</title>
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
            margin: 12mm;
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
            color: #172731;
            font-size: 12px;
            line-height: 1.5;
            background: #ffffff;
        }
        .sheet {
            background: #ffffff;
            border-top: 5px solid #f59e0b;
        }
        .brand, .facts, .grid, .foot {
            width: 100%;
            border-collapse: collapse;
        }
        .brand {
            background: {{ $isLightHeader ? '#ffffff' : '#192c34' }};
            color: {{ $isLightHeader ? '#172731' : '#ffffff' }};
            @if($isLightHeader)
            border-bottom: 1px solid #dee5e4;
            @endif
            page-break-inside: avoid;
        }
        .brand td {
            padding: 14px 18px;
            vertical-align: middle;
        }
        .brand img {
            max-width: 152px;
            max-height: 48px;
            vertical-align: middle;
        }
        .agency {
            font-size: 13px;
            font-weight: bold;
            color: {{ $isLightHeader ? '#172731' : '#ffffff' }};
        }
        .contact {
            font-size: 10px;
            color: {{ $isLightHeader ? '#56676e' : '#dce3e6' }};
            margin-top: 2px;
        }
        .hero {
            padding: 18px 0 14px 0;
            border-bottom: 1px solid #bec9cb;
            text-align: {{ $align }};
        }
        .kind {
            color: #805308;
            font-weight: bold;
            font-size: 12px;
        }
        .hero h1 {
            font-size: 30px;
            line-height: 1.18;
            margin: 4px 0 2px 0;
            color: #172731;
        }
        .hero-sub {
            color: #56676e;
            font-size: 12px;
            margin: 2px 0 0 0;
        }
        .facts {
            margin: 12px 0;
            background: #f2f5f3;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .facts td {
            padding: 10px 11px;
            vertical-align: top;
            text-align: {{ $align }};
        }
        .facts small {
            display: block;
            color: #58696e;
            font-size: 10px;
        }
        .facts strong {
            display: block;
            font-size: 12px;
            margin-top: 3px;
            color: #172731;
        }
        .nowrap {
            white-space: nowrap;
        }
        .unit {
            margin-top: 18px;
        }
        .unit-heading {
            font-size: 17px;
            font-weight: bold;
            margin: 0 0 7px 0;
            text-align: {{ $align }};
            color: #172731;
            page-break-after: avoid;
        }
        .count {
            display: inline-block;
            color: #825100;
            font-size: 11px;
            font-weight: bold;
            background: #fff0ce;
            padding: 1px 7px;
            vertical-align: middle;
        }
        .grid {
            table-layout: fixed;
        }
        .grid th, .grid td {
            text-align: {{ $align }};
            padding: 7px 6px;
            border-bottom: 1px solid #dee5e4;
        }
        .grid th {
            font-size: 10.5px;
            color: #516169;
            background: #f2f5f3;
            font-weight: bold;
        }
        .grid td {
            font-size: 12px;
            color: #172731;
        }
        .grid tr {
            page-break-inside: avoid;
        }
        .grid thead {
            display: table-header-group;
        }
        .badge {
            color: #986100;
            font-weight: bold;
            padding: 0 5px;
        }
        .summary-text {
            background: #f2f5f3;
            padding: 12px 14px;
            text-align: {{ $align }};
            font-size: 12px;
            line-height: 1.6;
            color: #172731;
        }
        .summary-text p {
            margin: 0 0 6px 0;
        }
        .summary-text p:last-child {
            margin-bottom: 0;
        }
        .foot {
            border-top: 1px solid #c6d0d1;
            margin-top: 24px;
            color: #50616a;
            font-size: 10px;
            page-break-inside: avoid;
        }
        .foot td {
            padding: 9px 0;
        }
    </style>
</head>
<body>
<div class="sheet">

    {{-- Top Brand Bar --}}
    <table class="brand">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 38%;">
                    @if($logoFullPath)
                        <img src="{{ $logoFullPath }}" alt="">
                    @endif
                </td>
                <td style="text-align: right; width: 62%;">
                    <div class="agency">{{ $f($tenant->name) }}</div>
                    <div class="contact">
                        {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}
                    </div>
                </td>
            @else
                <td style="text-align: left; width: 62%;">
                    <div class="agency">{{ $tenant->name }}</div>
                    <div class="contact">
                        {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}
                    </div>
                </td>
                <td style="text-align: right; width: 38%;">
                    @if($logoFullPath)
                        <img src="{{ $logoFullPath }}" alt="">
                    @endif
                </td>
            @endif
        </tr>
    </table>

    {{-- Hero Title --}}
    <div class="hero">
        <div class="kind">{{ $f($t('ui.itinerary')) }}</div>
        <h1>{{ $f($trip->title) }}</h1>
        @if($trip->destination && $trip->destination !== $trip->title)
            <div class="hero-sub">{{ $f($trip->destination) }}</div>
        @endif
    </div>

    {{-- Facts Strip --}}
    @if(count($facts) > 0)
    <table class="facts">
        <tr>
            @foreach($facts as $fact)
                <td style="{{ !empty($fact['nowrap']) ? 'width: 31%;' : '' }}">
                    <small>{{ $f($fact['label']) }}</small>
                    <strong class="{{ !empty($fact['nowrap']) ? 'nowrap' : '' }}">
                        {{ $fact['is_text'] ? $f($fact['value']) : $fact['value'] }}
                        @if(!empty($fact['sub']))
                            <br>{{ $f($fact['sub']) }}
                        @endif
                    </strong>
                </td>
            @endforeach
        </tr>
    </table>
    @endif

    {{-- Flights --}}
    @if(count($flights) > 0)
    @php
        $hasAirports = collect($flights)->contains(fn ($fl) => !empty($fl['from_airport']) || !empty($fl['to_airport']));
    @endphp
    <div class="unit">
        <div class="unit-heading">
            @if($isRtl)
                <span class="count">{{ count($flights) }}</span> {{ $f($t('ui.flights')) }}
            @else
                {{ $t('ui.flights') }} <span class="count">{{ count($flights) }}</span>
            @endif
        </div>
        <table class="grid">
            <thead>
                <tr>
                    @if($isRtl)
                        <th>{{ $f($t('ui.departure_time')) }}</th>
                        <th>{{ $f($t('ui.flight_number')) }}</th>
                        <th>{{ $f($t('ui.airline')) }}</th>
                        @if($hasAirports)
                            <th>{{ $f($t('ui.flight_route')) }}</th>
                        @endif
                        <th>{{ $f($t('ui.flight_type')) }}</th>
                    @else
                        <th>{{ $t('ui.flight_type') }}</th>
                        @if($hasAirports)
                            <th>{{ $t('ui.flight_route') }}</th>
                        @endif
                        <th>{{ $t('ui.airline') }}</th>
                        <th>{{ $t('ui.flight_number') }}</th>
                        <th>{{ $t('ui.departure_time') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($flights as $flight)
                    @php
                        $flightType = $flight['type'] ?? 'outbound';
                        $depFormatted = !empty($flight['departure_time'])
                            ? \Carbon\Carbon::parse($flight['departure_time'])->format('d/m/Y H:i')
                            : '–';
                        $arrFormatted = !empty($flight['arrival_time'])
                            ? \Carbon\Carbon::parse($flight['arrival_time'])->format('d/m/Y H:i')
                            : null;
                        $timeDisplay = $arrFormatted ? ($depFormatted . ' → ' . $arrFormatted) : $depFormatted;
                        $fromCode = $flight['from_airport'] ?? '';
                        $toCode = $flight['to_airport'] ?? '';
                        $routeDisplay = ($fromCode || $toCode) ? trim($fromCode . ' → ' . $toCode, ' →') : '–';
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td class="nowrap">{{ $timeDisplay }}</td>
                            <td>{{ $flight['flight_number'] ?? '–' }}</td>
                            <td>{{ !empty($flight['airline']) ? $f($flight['airline']) : '–' }}</td>
                            @if($hasAirports)
                                <td>{{ $f($routeDisplay) }}</td>
                            @endif
                            <td>{{ $f($t("ui.{$flightType}")) }}</td>
                        @else
                            <td>{{ $t("ui.{$flightType}") }}</td>
                            @if($hasAirports)
                                <td>{{ $routeDisplay }}</td>
                            @endif
                            <td>{{ $flight['airline'] ?? '–' }}</td>
                            <td>{{ $flight['flight_number'] ?? '–' }}</td>
                            <td class="nowrap">{{ $timeDisplay }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Hotels --}}
    @if(count($hotels) > 0)
    <div class="unit">
        <div class="unit-heading">
            @if($isRtl)
                <span class="count">{{ count($hotels) }}</span> {{ $f($t('ui.hotels')) }}
            @else
                {{ $t('ui.hotels') }} <span class="count">{{ count($hotels) }}</span>
            @endif
        </div>
        <table class="grid">
            <thead>
                <tr>
                    @if($isRtl)
                        <th style="width: 12%;">{{ $f($t('ui.nights')) }}</th>
                        <th style="width: 13%;">{{ $f($roomsLabel) }}</th>
                        <th style="width: 18%;">{{ $f($checkOutLabel) }}</th>
                        <th style="width: 18%;">{{ $f($checkInLabel) }}</th>
                        <th style="width: 39%;">{{ $f($t('ui.hotel_name')) }}</th>
                    @else
                        <th style="width: 39%;">{{ $t('ui.hotel_name') }}</th>
                        <th style="width: 18%;">{{ $checkInLabel }}</th>
                        <th style="width: 18%;">{{ $checkOutLabel }}</th>
                        <th style="width: 13%;">{{ $roomsLabel }}</th>
                        <th style="width: 12%;">{{ $t('ui.nights') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($hotels as $hotel)
                    @php
                        $checkIn = !empty($hotel['from']) ? \Carbon\Carbon::parse($hotel['from'])->format('d/m/Y') : '–';
                        $checkOut = !empty($hotel['until']) ? \Carbon\Carbon::parse($hotel['until'])->format('d/m/Y') : '–';
                        $hNights = '–';
                        if (!empty($hotel['from']) && !empty($hotel['until'])) {
                            $d = (int) \Carbon\Carbon::parse($hotel['from'])->diffInDays(\Carbon\Carbon::parse($hotel['until']), false);
                            if ($d > 0) {
                                $hNights = (string) $d;
                            }
                        }
                        $rooms = $hotel['rooms'] ?? '–';
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td>{{ $hNights }}</td>
                            <td>{{ $rooms }}</td>
                            <td class="nowrap">{{ $checkOut }}</td>
                            <td class="nowrap">{{ $checkIn }}</td>
                            <td>{{ $f($hotel['name'] ?? '–') }}</td>
                        @else
                            <td>{{ $hotel['name'] ?? '–' }}</td>
                            <td class="nowrap">{{ $checkIn }}</td>
                            <td class="nowrap">{{ $checkOut }}</td>
                            <td>{{ $rooms }}</td>
                            <td>{{ $hNights }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Passengers --}}
    @if($passengersCount > 0)
    <div class="unit">
        <div class="unit-heading">
            @if($isRtl)
                <span class="count">{{ $passengersCount }}</span> {{ $f($t('ui.passengers')) }}
            @else
                {{ $t('ui.passengers') }} <span class="count">{{ $passengersCount }}</span>
            @endif
        </div>
        <table class="grid">
            <tbody>
                @foreach($passengers as $idx => $passenger)
                    <tr>
                        <td>
                            @if($isRtl)
                                {{ $f($passenger['name'] ?? '') }} <span class="badge">{{ $idx + 1 }}</span>
                            @else
                                <span class="badge">{{ $idx + 1 }}</span> {{ $passenger['name'] ?? '' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Trip Summary --}}
    @if(!empty($trip->trip_summary))
    <div class="unit">
        <div class="unit-heading">{{ $f($t('ui.trip_summary')) }}</div>
        <div class="summary-text">
            {!! $isRtl ? \App\Helpers\PdfHelper::fixRtl($trip->trip_summary) : $trip->trip_summary !!}
        </div>
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
