@php
    $lang = $tenant->language ?: 'he';
    $isRtl = $lang !== 'en';
    $isLightHeader = ($tenant->pdf_header_theme ?? 'dark') === 'light';
    $useOpenSans = $lang !== 'ar'
        && file_exists(storage_path('fonts/OpenSans-Regular.ttf'))
        && file_exists(storage_path('fonts/OpenSans-Bold.ttf'));
    $align = $isRtl ? 'right' : 'left';

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

    $currencySymbol = match ($tenant->currency ?? 'ILS') {
        'ILS' => '₪',
        'EUR' => '€',
        'GBP' => '£',
        default => '$',
    };

    $issueDateLabel = match ($lang) {
        'ar' => 'تاريخ الإصدار',
        'en' => 'Issue Date',
        default => 'תאריך הפקה',
    };

    $periodText = trim(
        ($fromDate ? \Carbon\Carbon::parse($fromDate)->format('d/m/Y') : '') .
        ($fromDate && $toDate ? ' – ' : '') .
        ($toDate ? \Carbon\Carbon::parse($toDate)->format('d/m/Y') : '')
    );

    $facts = [
        [
            'label' => $t('ui.total_collected'),
            'value' => $currencySymbol . ' ' . number_format((float) $totalRevenue, 2),
        ],
        [
            'label' => $t('ui.total_sales_volume'),
            'value' => $currencySymbol . ' ' . number_format((float) $totalSales, 2),
        ],
        [
            'label' => $t('ui.open_balances'),
            'value' => $currencySymbol . ' ' . number_format((float) $totalBalance, 2),
        ],
        [
            'label' => $t('ui.total_trips_count'),
            'value' => (string) $tripsCount,
        ],
    ];
    if ($isRtl) {
        $facts = array_reverse($facts);
    }
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $f($t('ui.sales_report')) }}</title>
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
            font-size: 11px;
            line-height: 1.45;
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
            max-height: 46px;
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
            padding: 16px 0 12px 0;
            border-bottom: 1px solid #bec9cb;
            text-align: {{ $align }};
        }
        .kind {
            color: #805308;
            font-weight: bold;
            font-size: 11px;
        }
        .hero h1 {
            font-size: 26px;
            line-height: 1.18;
            margin: 3px 0 0 0;
            color: #172731;
        }
        .period {
            font-size: 11px;
            color: #56676e;
            margin-top: 4px;
        }
        .facts {
            margin: 12px 0;
            background: #f2f5f3;
            table-layout: fixed;
            page-break-inside: avoid;
        }
        .facts td {
            padding: 10px 10px;
            vertical-align: top;
            text-align: {{ $align }};
            width: 25%;
        }
        .facts small {
            display: block;
            color: #58696e;
            font-size: 9.5px;
        }
        .facts strong {
            display: block;
            font-size: 13px;
            margin-top: 3px;
            color: #172731;
            white-space: nowrap;
        }
        .unit {
            margin-top: 16px;
        }
        .unit-heading {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 7px 0;
            text-align: {{ $align }};
            color: #172731;
            page-break-after: avoid;
        }
        .count {
            display: inline-block;
            color: #825100;
            font-size: 10.5px;
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
            padding: 6px 5px;
            border-bottom: 1px solid #dee5e4;
        }
        .grid th {
            font-size: 9.5px;
            color: #516169;
            background: #f2f5f3;
            font-weight: bold;
        }
        .grid td {
            font-size: 10.5px;
            color: #172731;
        }
        .grid tr {
            page-break-inside: avoid;
        }
        .grid thead {
            display: table-header-group;
        }
        .nowrap {
            white-space: nowrap;
        }
        .bal-due {
            color: #b91c1c;
            font-weight: bold;
        }
        .bal-paid {
            color: #15803d;
            font-weight: bold;
        }
        .foot {
            border-top: 1px solid #c6d0d1;
            margin-top: 22px;
            color: #50616a;
            font-size: 9.5px;
            page-break-inside: avoid;
        }
        .foot td {
            padding: 8px 0;
        }
    </style>
</head>
<body>
<div class="sheet">

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

    <div class="hero">
        <div class="kind">{{ $f($tenant->name) }}</div>
        <h1>{{ $f($t('ui.sales_report')) }}</h1>
        @if($periodText !== '')
            <div class="period">{{ $periodText }}</div>
        @endif
    </div>

    <table class="facts">
        <tr>
            @foreach($facts as $fact)
                <td>
                    <small>{{ $f($fact['label']) }}</small>
                    <strong>{{ $fact['value'] }}</strong>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="unit">
        <div class="unit-heading">
            @if($isRtl)
                <span class="count">{{ $trips->count() }}</span> {{ $f($t('ui.transactions_list')) }}
            @else
                {{ $t('ui.transactions_list') }} <span class="count">{{ $trips->count() }}</span>
            @endif
        </div>
        <table class="grid">
            <thead>
                <tr>
                    @if($isRtl)
                        <th style="width: 11%;">{{ $f($t('ui.status')) }}</th>
                        <th style="width: 13%;">{{ $f($t('ui.balance')) }}</th>
                        <th style="width: 13%;">{{ $f($t('ui.paid')) }}</th>
                        <th style="width: 13%;">{{ $f($t('ui.total_amount')) }}</th>
                        <th style="width: 12%;">{{ $f($t('ui.start_date')) }}</th>
                        <th style="width: 18%;">{{ $f($t('ui.destination')) }}</th>
                        <th style="width: 20%;">{{ $f($t('ui.customer')) }}</th>
                    @else
                        <th style="width: 20%;">{{ $t('ui.customer') }}</th>
                        <th style="width: 18%;">{{ $t('ui.destination') }}</th>
                        <th style="width: 12%;">{{ $t('ui.start_date') }}</th>
                        <th style="width: 13%;">{{ $t('ui.total_amount') }}</th>
                        <th style="width: 13%;">{{ $t('ui.paid') }}</th>
                        <th style="width: 13%;">{{ $t('ui.balance') }}</th>
                        <th style="width: 11%;">{{ $t('ui.status') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($trips as $trip)
                    @php
                        $paid = (float) $trip->payments->sum('amount');
                        $total = (float) $trip->total_amount;
                        $balance = $total - $paid;
                        $startDate = $trip->start_date ? $trip->start_date->format('d/m/Y') : '–';
                        $statusLabel = $f($t("ui.{$trip->status}"));
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td>{{ $statusLabel }}</td>
                            <td class="nowrap {{ $balance > 0 ? 'bal-due' : 'bal-paid' }}">{{ $currencySymbol }} {{ number_format($balance, 2) }}</td>
                            <td class="nowrap">{{ $currencySymbol }} {{ number_format($paid, 2) }}</td>
                            <td class="nowrap">{{ $currencySymbol }} {{ number_format($total, 2) }}</td>
                            <td class="nowrap">{{ $startDate }}</td>
                            <td>{{ $f($trip->destination ?: $trip->title) }}</td>
                            <td><strong>{{ $f($trip->customer?->name ?? '–') }}</strong></td>
                        @else
                            <td><strong>{{ $trip->customer?->name ?? '–' }}</strong></td>
                            <td>{{ $trip->destination ?: $trip->title }}</td>
                            <td class="nowrap">{{ $startDate }}</td>
                            <td class="nowrap">{{ $currencySymbol }} {{ number_format($total, 2) }}</td>
                            <td class="nowrap">{{ $currencySymbol }} {{ number_format($paid, 2) }}</td>
                            <td class="nowrap {{ $balance > 0 ? 'bal-due' : 'bal-paid' }}">{{ $currencySymbol }} {{ number_format($balance, 2) }}</td>
                            <td>{{ $statusLabel }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

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
