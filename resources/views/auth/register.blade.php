<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.register_page_title') }} — TOURISM-SALES</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if ($locale === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            @if ($locale === 'ar')
                font-family: 'Cairo', sans-serif;
            @else
                font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            @endif
            background: #f4f6fa;
            color: #0f172a;
            min-height: 100vh;
        }
        .split {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            min-height: 100vh;
        }
        .form-pane {
            padding: 32px clamp(20px, 5vw, 60px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }
        .form-wrap { max-width: 520px; width: 100%; margin: 0 auto; }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 12px;
        }
        .top-bar img { height: 34px; }
        .back-link {
            font-size: 0.82rem;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
        }
        .back-link:hover { color: #d97706; }
        h1 { margin: 0 0 6px; font-size: 1.65rem; font-weight: 800; color: #0f172a; }
        .sub { margin: 0 0 22px; font-size: 0.9rem; color: #64748b; }

        .plan-picker {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 18px;
        }
        .plan-opt {
            position: relative;
            cursor: pointer;
        }
        .plan-opt input { position: absolute; opacity: 0; pointer-events: none; }
        .plan-opt-box {
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 8px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.15s ease;
        }
        .plan-opt input:checked + .plan-opt-box {
            border-color: #f59e0b;
            background: #fffbeb;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
        .plan-opt-name { font-size: 0.8rem; font-weight: 800; color: #0f172a; }
        .plan-opt-price { font-size: 0.78rem; font-weight: 700; color: #d97706; margin-top: 2px; }
        .plan-opt-seats { font-size: 0.69rem; color: #64748b; margin-top: 2px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { margin-bottom: 13px; }
        .field label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 5px;
        }
        .field input, .field select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 0.9rem;
            font-family: inherit;
            color: #0f172a;
        }
        .field input:focus, .field select:focus {
            outline: none;
            border-color: #f59e0b;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
        }
        .err { color: #e11d48; font-size: 0.76rem; margin-top: 4px; font-weight: 600; }
        .submit-btn {
            width: 100%;
            padding: 13px 18px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            font-size: 0.96rem;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 8px 20px -6px rgba(245, 158, 11, 0.5);
            margin-top: 6px;
        }
        .submit-btn:hover { opacity: 0.95; }
        .foot-note {
            margin-top: 16px;
            text-align: center;
            font-size: 0.84rem;
            color: #64748b;
        }
        .foot-note a { color: #d97706; font-weight: 700; text-decoration: none; }

        .hero-pane {
            position: relative;
            background: linear-gradient(155deg, rgba(15, 23, 42, 0.88), rgba(11, 19, 32, 0.92)),
                        url('{{ asset('images/login-hero.jpg') }}') center/cover no-repeat;
            color: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero-list { list-style: none; padding: 0; margin: 24px 0 0; display: flex; flex-direction: column; gap: 14px; }
        .hero-list li { display: flex; align-items: center; gap: 10px; font-size: 0.95rem; color: #e2e8f0; }
        .hero-list b { color: #f59e0b; }

        @media (max-width: 900px) {
            .split { grid-template-columns: 1fr; }
            .hero-pane { display: none; }
            .grid-2, .plan-picker { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="split">
        <div class="form-pane">
            <div class="form-wrap">
                <div class="top-bar">
                    <a href="{{ route('landing') }}"><img src="{{ asset('images/logo-light.png') }}" alt="TOURISM-SALES"></a>
                    <a href="{{ route('landing') }}" class="back-link">← {{ __('ui.back_to_home') }}</a>
                </div>

                <h1>{{ __('ui.register_heading') }}</h1>
                <p class="sub">{{ __('ui.register_subheading') }}</p>

                <a
                    href="{{ route('auth.google.redirect', ['plan' => $selectedPlan?->slug ?? 'pro']) }}"
                    style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:11px 16px; border-radius:11px; border:1.5px solid #cbd5e1; background:#ffffff; color:#0f172a; font-size:0.9rem; font-weight:700; text-decoration:none; margin-bottom:14px; box-shadow:0 1px 3px rgba(15,23,42,0.06);"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
                        <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.565 24 12.255 24z"/>
                        <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/>
                        <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
                    </svg>
                    <span>{{ __('ui.signup_with_google') }}</span>
                </a>

                <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; color:#94a3b8; font-size:0.78rem;">
                    <span style="flex:1; height:1px; background:#e2e8f0;"></span>
                    <span>{{ __('ui.or_register_with_email') }}</span>
                    <span style="flex:1; height:1px; background:#e2e8f0;"></span>
                </div>

                <form method="POST" action="{{ route('register.store') }}">
                    @csrf

                    <label style="display:block; font-size:0.8rem; font-weight:700; color:#334155; margin-bottom:7px;">
                        {{ __('ui.select_your_plan') }}
                    </label>
                    <div class="plan-picker">
                        @foreach ($plans as $plan)
                            @php
                                $checked = old('plan_id', $selectedPlan?->id) == $plan->id;
                                $sym = $plan->getCurrencySymbol();
                                $priceVal = $billing === 'yearly'
                                    ? (int) round((float) $plan->yearly_price / 12)
                                    : (int) round((float) $plan->monthly_price);
                            @endphp
                            <label class="plan-opt">
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ $checked ? 'checked' : '' }} required>
                                <div class="plan-opt-box">
                                    <div class="plan-opt-name">{{ $plan->getLocalizedName($locale) }}</div>
                                    <div class="plan-opt-price">{{ $sym }}{{ number_format($priceVal) }}/{{ __('ui.month_short') }} {{ __('ui.plus_vat') }}</div>
                                    <div class="plan-opt-seats">{{ __('ui.plan_seats_short', ['count' => $plan->included_users]) }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('plan_id') <div class="err">{{ $message }}</div> @enderror

                    <div class="grid-2">
                        <div class="field">
                            <label>{{ __('ui.agency_name_label') }} *</label>
                            <input type="text" name="agency_name" value="{{ old('agency_name') }}" placeholder="{{ __('ui.agency_name_placeholder') }}" required>
                            @error('agency_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label>{{ __('ui.owner_name_label') }} *</label>
                            <input type="text" name="owner_name" value="{{ old('owner_name') }}" required>
                            @error('owner_name') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label>{{ __('ui.email') }} *</label>
                            <input type="email" name="email" value="{{ old('email') }}" dir="ltr" required>
                            @error('email') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label>{{ __('ui.phone') }} *</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="050-0000000" dir="ltr" required>
                            @error('phone') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label>{{ __('ui.password') }} *</label>
                            <input type="password" name="password" dir="ltr" required>
                            @error('password') <div class="err">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label>{{ __('ui.confirm_password') }} *</label>
                            <input type="password" name="password_confirmation" dir="ltr" required>
                        </div>
                    </div>

                    <div class="field">
                        <label>{{ __('ui.currency') }}</label>
                        <select name="currency">
                            <option value="ILS" {{ old('currency', 'ILS') === 'ILS' ? 'selected' : '' }}>₪ ILS</option>
                            <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>$ USD</option>
                            <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>€ EUR</option>
                        </select>
                    </div>

                    <button type="submit" class="submit-btn">
                        {{ __('ui.create_agency_trial_btn') }}
                    </button>
                </form>

                <div class="foot-note">
                    {{ __('ui.already_have_account') }}
                    <a href="/agent/login">{{ __('ui.login_btn') }}</a>
                    <div style="margin-top: 8px; font-size: 0.76rem; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                        <a href="{{ route('legal.privacy') }}" style="color:#64748b; font-weight:600;">{{ __('ui.privacy_policy') }}</a>
                        <a href="{{ route('legal.accessibility') }}" style="color:#64748b; font-weight:600;">{{ __('ui.accessibility_statement') }}</a>
                        <a href="{{ route('legal.terms') }}" style="color:#64748b; font-weight:600;">{{ __('ui.terms_of_service') }}</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-pane">
            <div>
                <img src="{{ asset('images/logo-dark.png') }}" alt="TOURISM-SALES" style="height: 40px;">
            </div>
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; line-height: 1.25; margin: 0;">
                    {{ __('ui.register_side_title') }}
                </h2>
                <ul class="hero-list">
                    <li><b>✓</b> {{ __('ui.register_perk_1') }}</li>
                    <li><b>✓</b> {{ __('ui.register_perk_2') }}</li>
                    <li><b>✓</b> {{ __('ui.register_perk_3') }}</li>
                    <li><b>✓</b> {{ __('ui.register_perk_4') }}</li>
                </ul>
            </div>
            <div style="font-size: 0.85rem; color: #94a3b8;">
                {{ __('ui.need_help_whatsapp') }}
                <a href="https://api.whatsapp.com/send?phone={{ $contactPhoneIntl }}" target="_blank" rel="noopener" style="color: #fbbf24; font-weight: 700;" dir="ltr">{{ $contactPhoneDisplay }}</a>
            </div>
        </div>
    </div>

    @include('components.site-compliance')
</body>
</html>
