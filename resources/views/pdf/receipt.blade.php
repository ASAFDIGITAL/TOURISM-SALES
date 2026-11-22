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

    $thankYouText = match ($lang) {
        'ar' => 'شكراً لتعاملكم معنا',
        'en' => 'Thank you for choosing us',
        default => 'תודה שבחרתם בנו',
    };
    $issueDateLabel = match ($lang) {
        'ar' => 'تاريخ الإصدار',
        'en' => 'Issue Date',
        default => 'תאריך הפקה',
    };
    $paymentsDetailsLabel = match ($lang) {
        'ar' => 'تفاصيل المدفوعات',
        'en' => 'Payment Details',
        default => 'פירוט תשלומים',
    };

    $issueDate = $receipt->created_at ? $receipt->created_at->format('d/m/Y') : now()->format('d/m/Y');
    $total = $receipt->payments->sum('amount');

    $facts = [
        [
            'label' => $t('ui.customer'),
            'value' => $customer?->name ?? '–',
            'sub' => $customer?->phone ?: ($customer?->email ?: null),
            'is_text' => true,
        ],
        [
            'label' => $t('ui.trip'),
            'value' => $trip?->title ?? '–',
            'sub' => null,
            'is_text' => true,
        ],
        [
            'label' => $t('ui.paid_at'),
            'value' => $issueDate,
            'sub' => null,
            'is_text' => false,
        ],
        [
            'label' => $t('ui.receipt_number'),
            'value' => (string) $receipt->receipt_number,
            'sub' => null,
            'is_text' => false,
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
    <title>{{ $f($t('ui.receipt')) }} · {{ $receipt->receipt_number }}</title>
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
        .brand, .facts, .grid, .sum, .foot {
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
            margin: 4px 0 0 0;
            color: #172731;
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
            width: 25%;
        }
        .facts small, .sum small {
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
        .nowrap {
            white-space: nowrap;
        }
        .sum {
            background: #fff2d5;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .sum td {
            padding: 16px 18px;
            vertical-align: middle;
        }
        .total {
            font-size: 24px;
            font-weight: bold;
            color: #352710;
            margin-top: 2px;
            white-space: nowrap;
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

    {{-- Hero --}}
    <div class="hero">
        <div class="kind">{{ $f($t('ui.receipt')) }}</div>
        <h1>{{ $receipt->receipt_number }}</h1>
    </div>

    {{-- Facts Strip --}}
    <table class="facts">
        <tr>
            @foreach($facts as $fact)
                <td>
                    <small>{{ $f($fact['label']) }}</small>
                    <strong>
                        {{ $fact['is_text'] ? $f($fact['value']) : $fact['value'] }}
                        @if(!empty($fact['sub']))
                            <br>{{ $f($fact['sub']) }}
                        @endif
                    </strong>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Payments Breakdown --}}
    <div class="unit">
        <div class="unit-heading">
            @if($isRtl)
                <span class="count">{{ $receipt->payments->count() }}</span> {{ $f($paymentsDetailsLabel) }}
            @else
                {{ $paymentsDetailsLabel }} <span class="count">{{ $receipt->payments->count() }}</span>
            @endif
        </div>
        <table class="grid">
            <thead>
                <tr>
                    @if($isRtl)
                        <th>{{ $f($t('ui.amount')) }}</th>
                        <th>{{ $f($t('ui.paid_at')) }}</th>
                        <th>{{ $f($t('ui.cheque_number')) }}</th>
                        <th>{{ $f($t('ui.method')) }}</th>
                        <th>{{ $f($t('ui.trip')) }}</th>
                    @else
                        <th>{{ $t('ui.trip') }}</th>
                        <th>{{ $t('ui.method') }}</th>
                        <th>{{ $t('ui.cheque_number') }}</th>
                        <th>{{ $t('ui.paid_at') }}</th>
                        <th>{{ $t('ui.amount') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->payments as $p)
                    @php
                        $paidDate = $p->paid_at ? $p->paid_at->format('d/m/Y') : $issueDate;
                        $formattedAmt = $currencySymbol . ' ' . number_format((float) $p->amount, 2);
                    @endphp
                    <tr>
                        @if($isRtl)
                            <td class="nowrap"><strong>{{ $formattedAmt }}</strong></td>
                            <td class="nowrap">{{ $paidDate }}</td>
                            <td>{{ $p->cheque_number ?: '–' }}</td>
                            <td>{{ $f($t("ui.{$p->method}")) }}</td>
                            <td>{{ $f($trip?->title ?? '–') }}</td>
                        @else
                            <td>{{ $trip?->title ?? '–' }}</td>
                            <td>{{ $t("ui.{$p->method}") }}</td>
                            <td>{{ $p->cheque_number ?: '–' }}</td>
                            <td class="nowrap">{{ $paidDate }}</td>
                            <td class="nowrap"><strong>{{ $formattedAmt }}</strong></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Summary Total Bar --}}
    <table class="sum">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 55%; color: #172731;">
                    {{ $f($thankYouText) }}<br>
                    <strong>{{ $f($tenant->name) }}</strong>
                </td>
                <td style="text-align: right; width: 45%;">
                    <small>{{ $f($t('ui.total_amount')) }}</small>
                    <div class="total">{{ $currencySymbol }} {{ number_format((float) $total, 2) }}</div>
                </td>
            @else
                <td style="text-align: left; width: 45%;">
                    <small>{{ $t('ui.total_amount') }}</small>
                    <div class="total">{{ $currencySymbol }} {{ number_format((float) $total, 2) }}</div>
                </td>
                <td style="text-align: right; width: 55%; color: #172731;">
                    {{ $thankYouText }}<br>
                    <strong>{{ $tenant->name }}</strong>
                </td>
            @endif
        </tr>
    </table>

    {{-- Footer --}}
    <table class="foot">
        <tr>
            @if($isRtl)
                <td style="text-align: left; width: 35%;">
                    {{ $issueDate }} {{ $f($issueDateLabel) }}
                </td>
                <td style="text-align: right; width: 65%;">
                    {{ $tenant->email }}@if($tenant->email && $tenant->phone) · @endif{{ $tenant->phone }}@if(($tenant->email || $tenant->phone) && $tenant->name) · @endif{{ $f($tenant->name) }}
                </td>
            @else
                <td style="text-align: left; width: 65%;">
                    {{ $tenant->name }}@if($tenant->name && $tenant->phone) · @endif{{ $tenant->phone }}@if(($tenant->name || $tenant->phone) && $tenant->email) · @endif{{ $tenant->email }}
                </td>
                <td style="text-align: right; width: 35%;">
                    {{ $issueDateLabel }} {{ $issueDate }}
                </td>
            @endif
        </tr>
    </table>

</div>
</body>
</html>
