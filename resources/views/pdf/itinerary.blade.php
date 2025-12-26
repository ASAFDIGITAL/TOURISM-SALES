@php
    $isRtl = $tenant->language !== 'en';
    $f = function($text) use ($isRtl) {
        return $isRtl ? \App\Helpers\PdfHelper::fixRtl($text) : $text;
    };
    
    // Helper for Label: Value in RTL
    $lv = function($label, $value) use ($f, $isRtl) {
        if ($isRtl) {
            // To show "Label: Value" in RTL (rendered LTR), we need "Value :Label"
            return $f($value) . ' :' . $f($label);
        }
        return '<strong>' . $label . ':</strong> ' . $value;
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant->language }}" dir="{{ $tenant->language === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $f(__('ui.itinerary')) }} - {{ $f($trip->title) }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: {{ $tenant->language === 'en' ? 'ltr' : 'rtl' }};
            text-align: {{ $tenant->language === 'en' ? 'left' : 'right' }};
            padding: 20px;
            color: #333;
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 8px 12px;
            border-right: {{ $isRtl ? '4px solid #000' : 'none' }};
            border-left: {{ !$isRtl ? '4px solid #000' : 'none' }};
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .item-box {
            border: 1px solid #eee;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8em;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div style="float: {{ $isRtl ? 'left' : 'left' }}; width: 30%;">
            @if($tenant->logo_path && file_exists(storage_path('app/public/' . $tenant->logo_path)))
                <img src="{{ storage_path('app/public/' . $tenant->logo_path) }}" style="max-width: 150px;" alt="Logo">
            @endif
        </div>
        <div style="float: {{ $isRtl ? 'right' : 'right' }}; width: 65%; text-align: {{ $isRtl ? 'right' : 'left' }};">
            <h1 style="margin: 0; font-size: 1.8em;">{{ $f($trip->title) }}</h1>
            <p style="margin: 5px 0; color: #666;">{{ $f($trip->destination) }} | {{ $trip->start_date?->format('d/m/Y') }} - {{ $trip->end_date?->format('d/m/Y') }}</p>
        </div>
        <div style="clear: both;"></div>
    </div>

    @if(!empty($trip->hotels))
    <div class="section">
        <div class="section-title">{{ $f(__('ui.hotels')) }}</div>
        @foreach($trip->hotels as $hotel)
            <div class="item-box">
                <div style="font-weight: bold; font-size: 1.1em; margin-bottom: 5px;">{{ $f($hotel['name']) }}</div>
                <div style="font-size: 0.95em;">
                    @if($isRtl)
                        {!! $lv(__('ui.rooms_count'), $hotel['rooms']) !!}
                        <span style="margin: 0 10px;">|</span>
                        {!! $lv(__('ui.end_date'), \Carbon\Carbon::parse($hotel['until'])->format('d/m/Y')) !!}
                        <span style="margin: 0 10px;">|</span>
                        {!! $lv(__('ui.start_date'), \Carbon\Carbon::parse($hotel['from'])->format('d/m/Y')) !!}
                    @else
                        {!! $lv(__('ui.start_date'), \Carbon\Carbon::parse($hotel['from'])->format('d/m/Y')) !!}
                        <span style="margin: 0 10px;">|</span>
                        {!! $lv(__('ui.end_date'), \Carbon\Carbon::parse($hotel['until'])->format('d/m/Y')) !!}
                        <span style="margin: 0 10px;">|</span>
                        {!! $lv(__('ui.rooms_count'), $hotel['rooms']) !!}
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if(!empty($trip->flights))
    <div class="section">
        <div class="section-title">{{ $f(__('ui.flights')) }}</div>
        <table>
            <thead>
                <tr style="background: #fafafa;">
                    @if($isRtl)
                        <th style="text-align: left;">{{ $f(__('ui.departure_time')) }}</th>
                        <th style="text-align: center;">{{ $f(__('ui.flight_number')) }}</th>
                        <th style="text-align: center;">{{ $f(__('ui.airline')) }}</th>
                        <th style="text-align: right;">{{ $f(__('ui.flight_type')) }}</th>
                    @else
                        <th style="text-align: left;">{{ $f(__('ui.flight_type')) }}</th>
                        <th style="text-align: center;">{{ $f(__('ui.airline')) }}</th>
                        <th style="text-align: center;">{{ $f(__('ui.flight_number')) }}</th>
                        <th style="text-align: right;">{{ $f(__('ui.departure_time')) }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($trip->flights as $flight)
                <tr>
                    @if($isRtl)
                        <td style="text-align: left;">{{ \Carbon\Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') }}</td>
                        <td style="text-align: center;">{{ $flight['flight_number'] }}</td>
                        <td style="text-align: center;">{{ $f($flight['airline']) }}</td>
                        <td style="text-align: right;">{{ $f(__("ui.{$flight['type']}")) }}</td>
                    @else
                        <td style="text-align: left;">{{ $f(__("ui.{$flight['type']}")) }}</td>
                        <td style="text-align: center;">{{ $f($flight['airline']) }}</td>
                        <td style="text-align: center;">{{ $flight['flight_number'] }}</td>
                        <td style="text-align: right;">{{ \Carbon\Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($trip->passengers))
    <div class="section">
        <div class="section-title">{{ $f(__('ui.passengers')) }}</div>
        <div style="column-count: 2; column-gap: 20px;">
            @foreach($trip->passengers as $idx => $passenger)
                <div style="padding: 4px 0; border-bottom: 1px dotted #eee;">
                    @if($isRtl)
                        {{ $f($passenger['name']) }} <span style="color: #999;">.{{ $idx + 1 }}</span>
                    @else
                        <span style="color: #999;">{{ $idx + 1 }}.</span> {{ $f($passenger['name']) }}
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($trip->trip_summary)
    <div class="section">
        <div class="section-title">{{ $f(__('ui.trip_summary')) }}</div>
        <div style="line-height: 1.6;">
            {!! $isRtl ? \App\Helpers\PdfHelper::fixRtl($trip->trip_summary) : $trip->trip_summary !!}
        </div>
    </div>
    @endif

    <div class="footer">
        {{ $f($tenant->name) }} | {{ $tenant->email }} | {{ $f($tenant->phone) }}
    </div>

</body>
</html>
