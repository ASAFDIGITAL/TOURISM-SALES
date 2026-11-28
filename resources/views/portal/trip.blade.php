@php
    $isRtl = in_array($locale, ['he', 'ar'], true);
    $currency = $tenant?->currency ?? 'ILS';
    $sym = match ($currency) {
        'ILS' => '₪',
        'EUR' => '€',
        'GBP' => '£',
        default => '$',
    };
    $flights = is_array($trip->flights) ? $trip->flights : [];
    $hotels = is_array($trip->hotels) ? $trip->hotels : [];
    $passengers = is_array($trip->passengers) ? $trip->passengers : [];
    $total = (float) $trip->total_amount;
    $paid = (float) $trip->paid_amount;
    $balance = max(0, round($total - $paid, 2));
    $daysUntil = $trip->start_date ? (int) now()->startOfDay()->diffInDays($trip->start_date->copy()->startOfDay(), false) : null;
    $waPhone = $tenant?->phone ? app(\App\Services\TripMessageService::class)->normalizePhoneForWhatsApp($tenant->phone) : null;
    $logoUrl = ($tenant?->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($tenant->logo_path))
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->logo_path)
        : asset('images/logo-dark.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $trip->title }} — {{ $tenant?->name ?? 'TOURISM-SALES' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if ($locale === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            @if ($locale === 'ar')
                font-family: 'Cairo', sans-serif;
            @else
                font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            @endif
            line-height: 1.65;
            padding-bottom: 84px;
        }
        .portal-hero {
            background: radial-gradient(circle at 20% 20%, rgba(245, 158, 11, 0.24), transparent 52%),
                        linear-gradient(155deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 26px 20px 64px;
            border-bottom: 4px solid #f59e0b;
        }
        .wrap { max-width: 920px; margin: 0 auto; padding: 0 16px; }
        .agency-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }
        .agency-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .agency-brand img {
            height: 40px;
            max-width: 150px;
            object-fit: contain;
            border-radius: 8px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
        }
        .pill-quote { background: rgba(168, 85, 247, 0.22); border: 1px solid rgba(192, 132, 252, 0.5); color: #e9d5ff; }
        .pill-confirmed { background: rgba(16, 185, 129, 0.22); border: 1px solid rgba(52, 211, 153, 0.5); color: #a7f3d0; }
        .pill-draft { background: rgba(245, 158, 11, 0.22); border: 1px solid rgba(251, 191, 36, 0.5); color: #fde68a; }
        .hero-title { margin: 0 0 8px; font-size: clamp(1.5rem, 4vw, 2.15rem); font-weight: 800; line-height: 1.25; }
        .hero-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 0.9rem; color: #cbd5e1; }
        .content-stack { margin-top: -40px; display: flex; flex-direction: column; gap: 18px; }
        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 22px 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 30px -18px rgba(15, 23, 42, 0.25);
        }
        .card h2 {
            margin: 0 0 14px;
            font-size: 1.08rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quote-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 2px solid #f59e0b;
        }
        .approved-banner {
            background: #ecfdf5;
            border: 1.5px solid #10b981;
            color: #065f46;
            border-radius: 14px;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 0.92rem;
        }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .kpi {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 13px;
            padding: 14px 16px;
        }
        .kpi small { display: block; font-size: 0.76rem; color: #64748b; font-weight: 700; }
        .kpi strong { display: block; font-size: 1.35rem; font-weight: 800; margin-top: 3px; }
        .item-row {
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
        }
        .item-row:last-child { margin-bottom: 0; }
        .btn-gold {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 11px;
            border: none;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 18px -6px rgba(245, 158, 11, 0.55);
        }
        .btn-navy {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 10px;
            background: #0f172a;
            color: #ffffff;
            font-size: 0.86rem;
            font-weight: 700;
            text-decoration: none;
        }
        .doc-list { display: flex; flex-wrap: wrap; gap: 10px; }
        .wa-fab {
            position: fixed;
            bottom: 18px;
            left: 18px;
            z-index: 40;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            background: #25d366;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.88rem;
            text-decoration: none;
            box-shadow: 0 12px 26px -6px rgba(37, 211, 102, 0.55);
        }
        @media (max-width: 680px) {
            .grid-3 { grid-template-columns: 1fr; }
            .card { padding: 18px 16px; }
        }
    </style>
</head>
<body>
    <header class="portal-hero">
        <div class="wrap">
            <div class="agency-bar">
                <div class="agency-brand">
                    <img src="{{ $logoUrl }}" alt="{{ $tenant?->name ?? 'TOURISM-SALES' }}">
                    <div>
                        <div style="font-weight: 800; font-size: 1rem;">{{ $tenant?->name }}</div>
                        @if ($tenant?->phone || $tenant?->email)
                            <div style="font-size: 0.78rem; color: #94a3b8;" dir="ltr">
                                {{ implode(' · ', array_filter([$tenant?->phone, $tenant?->email])) }}
                            </div>
                        @endif
                    </div>
                </div>

                @php
                    $pillClass = match ($trip->status) {
                        'quote' => 'pill-quote',
                        'confirmed', 'completed' => 'pill-confirmed',
                        default => 'pill-draft',
                    };
                @endphp
                <span class="status-pill {{ $pillClass }}">
                    {{ __('ui.' . ($trip->status ?: 'draft')) }}
                </span>
            </div>

            <h1 class="hero-title">{{ $trip->title }}</h1>
            <div class="hero-meta">
                @if ($trip->customer?->name)
                    <span>👤 {{ $trip->customer->name }}</span>
                @endif
                @if ($trip->destination)
                    <span>📍 {{ $trip->destination }}</span>
                @endif
                @if ($trip->start_date)
                    <span>
                        📅 {{ $trip->start_date->format('d/m/Y') }}
                        @if ($trip->end_date) – {{ $trip->end_date->format('d/m/Y') }} @endif
                    </span>
                @endif
                @if ($daysUntil !== null && $daysUntil >= 0 && $trip->status !== 'cancelled')
                    <span style="color: #fbbf24; font-weight: 700;">
                        ✈️ {{ $daysUntil === 0 ? __('ui.portal_departs_today') : __('ui.portal_days_countdown', ['days' => $daysUntil]) }}
                    </span>
                @endif
            </div>
        </div>
    </header>

    <main class="wrap content-stack">
        @if (session('quote_approved_success'))
            <div class="approved-banner">
                ✓ {{ __('ui.portal_quote_approved_thanks') }}
            </div>
        @endif

        {{-- 1-Click Quote Approval Card --}}
        @if ($trip->isQuote())
            <section class="card quote-box">
                <h2>📝 {{ __('ui.portal_quote_approval_heading') }}</h2>
                <p style="margin: 0 0 14px; font-size: 0.9rem; color: #78350f;">
                    {{ __('ui.portal_quote_approval_desc', ['total' => $sym . number_format($total, 2)]) }}
                </p>
                <form method="POST" action="{{ route('trip.portal.approve-quote', ['token' => $trip->public_token]) }}" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    @csrf
                    <input
                        type="text"
                        name="approver_name"
                        value="{{ old('approver_name', $trip->customer?->name ?? '') }}"
                        placeholder="{{ __('ui.portal_your_full_name') }}"
                        required
                        style="flex: 1; min-width: 210px; padding: 11px 14px; border-radius: 10px; border: 1.5px solid #d97706; background: #ffffff; font-size: 0.92rem; font-family: inherit;"
                    >
                    <button type="submit" class="btn-gold">
                        ✓ {{ __('ui.portal_approve_quote_btn') }}
                    </button>
                </form>
            </section>
        @elseif ($trip->quote_approved_at)
            <div class="approved-banner">
                ✓ {{ __('ui.portal_quote_approved_by_on', ['name' => $trip->quote_approved_by_name ?: ($trip->customer?->name ?? ''), 'date' => $trip->quote_approved_at->format('d/m/Y H:i')]) }}
            </div>
        @endif

        {{-- Payment Summary --}}
        <section class="card">
            <h2>💳 {{ __('ui.portal_payment_summary') }}</h2>
            <div class="grid-3">
                <div class="kpi">
                    <small>{{ __('ui.total_amount') }}</small>
                    <strong>{{ $sym }}{{ number_format($total, 2) }}</strong>
                </div>
                <div class="kpi">
                    <small>{{ __('ui.paid_amount') }}</small>
                    <strong style="color: #059669;">{{ $sym }}{{ number_format($paid, 2) }}</strong>
                </div>
                <div class="kpi">
                    <small>{{ __('ui.balance') }}</small>
                    <strong style="color: {{ $balance > 0 ? '#d97706' : '#059669' }};">{{ $sym }}{{ number_format($balance, 2) }}</strong>
                </div>
            </div>
        </section>

        {{-- Flights --}}
        @if (count($flights) > 0)
            <section class="card">
                <h2>✈️ {{ __('ui.flights') }}</h2>
                @foreach ($flights as $flight)
                    <div class="item-row">
                        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-weight:800;">
                            <span>
                                {{ $flight['airline'] ?? '' }} {{ $flight['flight_number'] ?? '' }}
                            </span>
                            @if (! empty($flight['from_airport']) || ! empty($flight['to_airport']))
                                <span dir="ltr" style="color:#d97706;">
                                    {{ $flight['from_airport'] ?? '' }} → {{ $flight['to_airport'] ?? '' }}
                                </span>
                            @endif
                        </div>
                        <div style="font-size:0.84rem; color:#475569; margin-top:4px; display:flex; gap:14px; flex-wrap:wrap;">
                            @if (! empty($flight['departure_time']))
                                <span>🛫 {{ \Carbon\Carbon::parse($flight['departure_time'])->format('d/m/Y H:i') }}</span>
                            @endif
                            @if (! empty($flight['arrival_time']))
                                <span>🛬 {{ \Carbon\Carbon::parse($flight['arrival_time'])->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Hotels / Apartments --}}
        @if (count($hotels) > 0)
            @php
                $portalStayTypes = collect($hotels)->map(fn ($h) => ($h['type'] ?? 'hotel') === 'apartment' ? 'apartment' : 'hotel')->unique();
                $portalStayHeading = match (true) {
                    $portalStayTypes->contains('apartment') && $portalStayTypes->contains('hotel') => __('ui.hotel_and_apartment_stay_heading'),
                    $portalStayTypes->contains('apartment') => __('ui.apartment_stay_heading'),
                    default => __('ui.hotel_stay_heading'),
                };
            @endphp
            <section class="card">
                <h2>{{ $portalStayTypes->contains('apartment') && ! $portalStayTypes->contains('hotel') ? '🏠' : '🏨' }} {{ $portalStayHeading }}</h2>
                @foreach ($hotels as $hotel)
                    @php
                        $isApt = ($hotel['type'] ?? 'hotel') === 'apartment';
                    @endphp
                    <div class="item-row">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="padding: 2px 9px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 800;">
                                {{ $isApt ? __('ui.accommodation_type_apartment') : __('ui.accommodation_type_hotel') }}
                            </span>
                            <span style="font-weight: 800; font-size: 0.96rem;">{{ $hotel['name'] ?? '-' }}</span>
                        </div>
                        @if (! empty($hotel['address']))
                            <div style="font-size: 0.84rem; color: #334155; margin-top: 4px;">
                                📍 {{ $hotel['address'] }}
                            </div>
                        @endif
                        <div style="font-size: 0.84rem; color: #475569; margin-top: 4px; display: flex; gap: 14px; flex-wrap: wrap;">
                            @if (! empty($hotel['from']))
                                <span>📅 {{ \Carbon\Carbon::parse($hotel['from'])->format('d/m/Y') }} – {{ ! empty($hotel['until']) ? \Carbon\Carbon::parse($hotel['until'])->format('d/m/Y') : '' }}</span>
                            @endif
                            @if (! empty($hotel['rooms']))
                                <span>🛏 {{ $hotel['rooms'] }} {{ __('ui.rooms') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Passengers & Summary --}}
        @if (count($passengers) > 0 || filled($trip->trip_summary))
            <section class="card">
                <h2>🧳 {{ __('ui.portal_trip_details') }}</h2>
                @if (count($passengers) > 0)
                    <div style="margin-bottom: 12px;">
                        <strong style="font-size: 0.88rem; display: block; margin-bottom: 6px;">{{ __('ui.passengers') }}:</strong>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            @foreach ($passengers as $p)
                                @if (! empty($p['name']))
                                    <span style="padding: 5px 12px; border-radius: 999px; background: #f1f5f9; border: 1px solid #cbd5e1; font-size: 0.84rem; font-weight: 600;">
                                        {{ $p['name'] }}
                                        @if (! empty($p['passport_number']))
                                            <span style="color: #64748b; font-weight: 500;" dir="ltr"> · 🛂 {{ $p['passport_number'] }}</span>
                                        @endif
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (filled($trip->trip_summary))
                    <div style="font-size: 0.92rem; color: #334155;">
                        {!! nl2br(e(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $trip->trip_summary)))) !!}
                    </div>
                @endif
            </section>
        @endif

        {{-- Documents & Downloads --}}
        <section class="card">
            <h2>📄 {{ __('ui.portal_documents_heading') }}</h2>
            <div class="doc-list">
                <a href="{{ route('trip.portal.itinerary', ['token' => $trip->public_token, 'lang' => 'he']) }}" class="{{ $locale === 'he' ? 'btn-gold' : 'btn-navy' }}">
                    ⬇ {{ __('ui.download_itinerary') }} (עברית)
                </a>
                <a href="{{ route('trip.portal.itinerary', ['token' => $trip->public_token, 'lang' => 'ar']) }}" class="{{ $locale === 'ar' ? 'btn-gold' : 'btn-navy' }}">
                    ⬇ {{ __('ui.download_itinerary') }} (العربية)
                </a>
                <a href="{{ route('trip.portal.itinerary', ['token' => $trip->public_token, 'lang' => 'en']) }}" class="{{ $locale === 'en' ? 'btn-gold' : 'btn-navy' }}">
                    ⬇ {{ __('ui.download_itinerary') }} (English)
                </a>

                @foreach ($trip->attachments as $att)
                    <a href="{{ route('trip.portal.attachment', ['token' => $trip->public_token, 'attachment' => $att->id]) }}" class="btn-navy">
                        📎 {{ $att->name ?: __('ui.document') }}
                    </a>
                @endforeach
            </div>
        </section>
    </main>

    @if ($waPhone)
        <a
            href="https://api.whatsapp.com/send?phone={{ $waPhone }}&text={{ rawurlencode(__('ui.portal_wa_prefill', ['trip' => $trip->title])) }}"
            target="_blank"
            rel="noopener"
            class="wa-fab"
        >
            💬 {{ __('ui.portal_contact_agent_wa') }}
        </a>
    @endif
</body>
</html>
