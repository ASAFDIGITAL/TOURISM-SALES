@php
    $isRtl = $tenant->language !== 'en';
    $f = function($text) use ($isRtl) {
        return $isRtl ? \App\Helpers\PdfHelper::fixRtl($text) : $text;
    };
    // Helper to combine label and value and reverse the whole line
    $line = function($label, $value) use ($f, $isRtl) {
        if ($isRtl) {
            // In RTL, we want Label: Value. 
            // Reversed this becomes [Reversed Value] [Reversed Label:]
            return $f($label . ': ' . $value);
        }
        return '<strong>' . $label . ':</strong> ' . $value;
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant->language }}" dir="{{ $tenant->language === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: {{ $tenant->language === 'en' ? 'ltr' : 'rtl' }};
            text-align: {{ $tenant->language === 'en' ? 'left' : 'right' }};
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            height: auto;
        }
        .details-box {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 20px;
        }
        .row {
            width: 100%;
            margin-bottom: 20px;
        }
        .col {
            display: inline-block;
            width: 48%;
            vertical-align: top;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: {{ $tenant->language === 'en' ? 'left' : 'right' }};
        }
        th {
            background-color: #f2f2f2;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8em;
            color: #777;
        }
        .text-bold { font-weight: bold; }
    </style>
</head>
<body>

    <div class="header" style="border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
        <div style="float: left; width: 30%; text-align: left;">
            @if($tenant->logo_path && file_exists(storage_path('app/public/' . $tenant->logo_path)))
                <img src="{{ storage_path('app/public/' . $tenant->logo_path) }}" class="logo" alt="Logo">
            @endif
        </div>
        <div style="float: right; width: 65%; text-align: right;">
            <p style="margin: 0; font-size: 1.1em; font-weight: bold;">{{ $f($tenant->name) }}</p>
            <p style="margin: 2px 0; font-size: 0.9em;">{{ $tenant->email }}</p>
            <p style="margin: 0; font-size: 0.9em;">{{ $tenant->phone }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Customer & Receipt Info Grouped on the right for RTL -->
    <div style="margin-bottom: 30px; text-align: {{ $isRtl ? 'right' : 'left' }};">
        <h2 style="margin: 0; font-size: 1.6em; font-weight: bold; margin-bottom: 10px;">{{ $f($customer->name) }}</h2>
        <p style="margin: 3px 0; font-size: 1em;">
            {!! $isRtl ? $f(__('ui.paid_at') . ': ' . $receipt->created_at->format('d/m/Y')) : '<strong>' . __('ui.paid_at') . ':</strong> ' . $receipt->created_at->format('d/m/Y') !!}
        </p>
        <p style="margin: 3px 0; font-size: 1em;">
            {!! $isRtl ? $f(__('ui.receipt_number') . ': ' . $receipt->receipt_number) : '<strong>' . __('ui.receipt_number') . ':</strong> ' . $receipt->receipt_number !!}
        </p>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="border-top: 1px solid #000; border-bottom: 1px solid #000;">
                @if($isRtl)
                    <th style="padding: 10px 5px; text-align: left; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.amount')) }}</th>
                    <th style="padding: 10px 5px; text-align: right; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.cheque_number')) }}</th>
                    <th style="padding: 10px 5px; text-align: right; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.method')) }}</th>
                    <th style="padding: 10px 5px; text-align: right; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.notes')) }}</th>
                @else
                    <th style="padding: 10px 5px; text-align: left; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.notes')) }}</th>
                    <th style="padding: 10px 5px; text-align: left; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.method')) }}</th>
                    <th style="padding: 10px 5px; text-align: left; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.cheque_number')) }}</th>
                    <th style="padding: 10px 5px; text-align: right; font-size: 0.8em; color: #000; text-transform: uppercase;">{{ $f(__('ui.amount')) }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach($receipt->payments as $p)
                @php $total += $p->amount; @endphp
                <tr style="border-bottom: 1px solid #eee;">
                    @if($isRtl)
                        <td style="padding: 15px 5px; text-align: left; font-weight: bold;">
                            {{ number_format($p->amount, 2) }} 
                            {{ match($tenant->currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' } }}
                        </td>
                        <td style="padding: 15px 5px; text-align: right;">{{ $p->cheque_number }}</td>
                        <td style="padding: 15px 5px; text-align: right;">{{ $f(__("ui.{$p->method}")) }}</td>
                        <td style="padding: 15px 5px; text-align: right;">{{ $f($trip->title) }}</td>
                    @else
                        <td style="padding: 15px 5px; text-align: left;">{{ $f($trip->title) }}</td>
                        <td style="padding: 15px 5px; text-align: left;">{{ $f(__("ui.{$p->method}")) }}</td>
                        <td style="padding: 15px 5px; text-align: left;">{{ $p->cheque_number }}</td>
                        <td style="padding: 15px 5px; text-align: right; font-weight: bold;">
                            {{ number_format($p->amount, 2) }} 
                            {{ match($tenant->currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' } }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 40px;">
        <div style="float: right; width: 40%; text-align: right;">
            <p style="font-size: 0.9em; margin-bottom: 5px; color: #666; text-transform: uppercase;">{{ $f(__('ui.total_amount')) }}</p>
            <p style="font-size: 1.8em; font-weight: bold; margin: 0;">
                {{ number_format($total, 2) }}
                {{ match($tenant->currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' } }}
            </p>
        </div>
        <div style="float: left; width: 50%; text-align: left; padding-top: 10px;">
            <p style="font-size: 1.2em; color: #000;">{{ $isRtl ? $f('❤ תודה רבה!') : '❤ Thank you!' }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer" style="position: absolute; bottom: 0; width: 100%; border-top: 1px solid #eee; padding-top: 10px;">
        <table style="width: 100%; border: none;">
            <tr>
                <td style="border: none; font-size: 0.8em; color: #666;">{{ $tenant->email }} | {{ $tenant->phone }}</td>
                <td style="border: none; text-align: right; font-size: 0.8em; color: #666;">Page 1 of 1</td>
            </tr>
        </table>
    </div>

</body>
</html>
