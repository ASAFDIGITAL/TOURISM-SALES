<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TOURISM-SALES — {{ __('ui.landing_meta_title') }}</title>
    <meta name="description" content="{{ __('ui.landing_meta_desc') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if ($locale === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; overflow-x: hidden; }
        body {
            margin: 0;
            overflow-x: hidden;
            width: 100%;
            @if ($locale === 'ar')
                font-family: 'Cairo', sans-serif;
            @else
                font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            @endif
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .container { max-width: 1180px; margin: 0 auto; padding: 0 20px; width: 100%; }

        /* Navbar */
        .nav {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(15, 23, 42, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: 72px;
        }
        .nav-logo img { height: 36px; display: block; }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        .nav-links a:hover { color: #fbbf24; }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .lang-group {
            display: inline-flex;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 9px;
            padding: 3px;
            gap: 2px;
        }
        .lang-btn {
            border: none;
            background: transparent;
            color: #cbd5e1;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 7px;
            cursor: pointer;
            font-family: inherit;
        }
        .lang-btn.active {
            background: #f59e0b;
            color: #0f172a;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 11px;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            border: none;
            transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
            font-family: inherit;
            white-space: nowrap;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            box-shadow: 0 8px 20px -6px rgba(245, 158, 11, 0.55);
        }
        .btn-outline-light {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        .btn-outline-light:hover { background: rgba(255, 255, 255, 0.15); }
        .btn-dark {
            background: #0f172a;
            color: #ffffff;
        }
        .btn-lg { padding: 14px 26px; font-size: 0.96rem; border-radius: 13px; }

        /* Hero */
        .hero {
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 20% 20%, rgba(245, 158, 11, 0.18), transparent 45%),
                        linear-gradient(155deg, #0b1320 0%, #0f172a 55%, #16243d 100%);
            color: #ffffff;
            padding: 72px 0 84px;
        }
        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            gap: 44px;
            align-items: center;
        }
        .hero-grid > * { min-width: 0; }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.05rem);
            line-height: 1.18;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .hero h1 span { color: #f59e0b; }
        .hero p {
            margin: 18px 0 28px;
            font-size: 1.05rem;
            color: #cbd5e1;
            max-width: 560px;
        }
        .hero-ctas {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-trust {
            margin-top: 26px;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .hero-trust span { display: inline-flex; align-items: center; gap: 6px; }
        .hero-trust b { color: #34d399; }

        /* Mockup Card */
        .mockup {
            position: relative;
            background: #131f33;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 30px 65px -15px rgba(0, 0, 0, 0.55);
        }
        .mockup-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .mockup-dots { display: flex; gap: 6px; }
        .mockup-dots i { width: 10px; height: 10px; border-radius: 9999px; display: block; }
        .mockup-kpis {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .mockup-kpi {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-top: 3px solid #f59e0b;
            border-radius: 12px;
            padding: 12px;
        }
        .mockup-kpi small { color: #94a3b8; font-size: 0.7rem; display: block; }
        .mockup-kpi strong { font-size: 1.15rem; color: #ffffff; display: block; margin-top: 3px; }
        .mockup-chart {
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 14px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 118px;
            margin-bottom: 14px;
        }
        .mockup-chart span {
            flex: 1;
            border-radius: 6px 6px 2px 2px;
            background: linear-gradient(180deg, #f59e0b 0%, rgba(245, 158, 11, 0.3) 100%);
        }
        .mockup-push {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(245, 158, 11, 0.4);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mockup-push-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f59e0b;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
        }

        /* Sections */
        .section { padding: 76px 0; }
        .section-alt { background: #ffffff; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .section-head { text-align: center; max-width: 680px; margin: 0 auto 48px; }
        .section-eyebrow {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 800;
            color: #d97706;
            background: #fef3c7;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 10px;
        }
        .section-head h2 {
            margin: 0;
            font-size: clamp(1.6rem, 3vw, 2.3rem);
            font-weight: 800;
            color: #0f172a;
        }
        .section-head p { margin: 10px 0 0; color: #64748b; font-size: 0.98rem; }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .feature-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .feature-card:hover {
            transform: translateY(-3px);
            border-color: rgba(245, 158, 11, 0.5);
            box-shadow: 0 14px 30px -12px rgba(15, 23, 42, 0.12);
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #0f172a;
            color: #f59e0b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }
        .feature-card h3 { margin: 0 0 8px; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
        .feature-card p { margin: 0; font-size: 0.88rem; color: #64748b; }

        /* Pricing */
        .billing-toggle-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 36px;
        }
        .billing-toggle {
            display: inline-flex;
            background: #e2e8f0;
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
        }
        .billing-opt {
            border: none;
            background: transparent;
            padding: 9px 18px;
            border-radius: 9px;
            font-weight: 700;
            font-size: 0.88rem;
            color: #475569;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .billing-opt.active {
            background: #0f172a;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
        }
        .save-pill {
            background: #10b981;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 9999px;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            align-items: stretch;
        }
        .plan-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px -4px rgba(15, 23, 42, 0.06);
        }
        .plan-card.featured {
            background: linear-gradient(180deg, #0f172a 0%, #162238 100%);
            color: #ffffff;
            border: 2px solid #f59e0b;
            box-shadow: 0 24px 50px -15px rgba(15, 23, 42, 0.38);
            transform: translateY(-6px);
        }
        .plan-ribbon {
            position: absolute;
            top: -13px;
            inset-inline-start: 24px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            font-size: 0.74rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 9999px;
        }
        .plan-name { font-size: 1.25rem; font-weight: 800; margin: 0 0 6px; }
        .plan-desc { font-size: 0.85rem; color: #64748b; margin: 0 0 18px; min-height: 44px; }
        .plan-card.featured .plan-desc { color: #cbd5e1; }
        .plan-price-row { display: flex; align-items: baseline; gap: 6px; margin-bottom: 4px; }
        .plan-price { font-size: 2.4rem; font-weight: 800; line-height: 1; }
        .plan-period { font-size: 0.86rem; color: #64748b; font-weight: 600; }
        .plan-card.featured .plan-period { color: #94a3b8; }
        .plan-annual-note { font-size: 0.78rem; color: #10b981; font-weight: 700; min-height: 20px; margin-bottom: 18px; }
        .plan-list {
            list-style: none;
            padding: 18px 0;
            margin: 0 0 20px;
            border-top: 1px solid #e2e8f0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 0.86rem;
        }
        .plan-card.featured .plan-list { border-top-color: rgba(255, 255, 255, 0.12); }
        .plan-list li { display: flex; align-items: flex-start; gap: 9px; }
        .plan-check { color: #10b981; font-weight: 800; flex-shrink: 0; }
        .plan-addons {
            margin-top: 28px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        /* FAQ */
        .faq-list { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
        .faq-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 22px;
        }
        .faq-item summary {
            font-weight: 700;
            font-size: 0.98rem;
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .faq-item p { margin: 10px 0 0; font-size: 0.9rem; color: #475569; }

        /* CTA Banner & Footer */
        .cta-box {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 22px;
            padding: 48px 32px;
            text-align: center;
            color: #ffffff;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .cta-box h2 { margin: 0 0 10px; font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 800; }
        .cta-box p { margin: 0 auto 24px; color: #cbd5e1; max-width: 560px; }
        .footer {
            background: #0b1320;
            color: #94a3b8;
            padding: 36px 0;
            font-size: 0.85rem;
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .wa-float {
            position: fixed;
            bottom: 22px;
            left: 22px;
            z-index: 45;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 18px;
            border-radius: 9999px;
            background: #16a34a;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.86rem;
            box-shadow: 0 12px 26px -6px rgba(22, 163, 74, 0.55);
        }
        .wa-float:hover { transform: translateY(-2px); }

        @media (max-width: 960px) {
            .hero-grid, .features-grid, .pricing-grid { grid-template-columns: minmax(0, 1fr); }
            .plan-card.featured { transform: none; }
            .nav-links { display: none; }
        }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .nav-inner { height: 62px; gap: 8px; }
            .nav-logo img { height: 26px; }
            .nav-actions { gap: 6px; }
            .nav-cta-trial { display: none; }
            .lang-btn { padding: 4px 6px; font-size: 0.7rem; }
            .nav-actions .btn { padding: 7px 11px; font-size: 0.78rem; border-radius: 9px; }
            .hero { padding: 36px 0 48px; }
            .hero-badge { font-size: 0.74rem; padding: 5px 11px; max-width: 100%; }
            .hero h1 { font-size: 1.75rem; }
            .hero p { font-size: 0.94rem; margin: 14px 0 22px; }
            .hero-ctas { flex-direction: column; align-items: stretch; }
            .hero-ctas .btn-lg { width: 100%; padding: 13px 18px; }
            .mockup { padding: 14px; border-radius: 16px; }
            .mockup-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
            .mockup-kpi { padding: 8px 6px; }
            .mockup-kpi small { font-size: 0.62rem; }
            .mockup-kpi strong { font-size: 0.86rem; }
            .mockup-chart { height: 90px; padding: 10px; gap: 6px; }
            .section { padding: 48px 0; }
            .billing-toggle { flex-wrap: wrap; justify-content: center; }
            .billing-opt { padding: 8px 12px; font-size: 0.8rem; }
            .plan-card { padding: 22px 18px; }
            .plan-addons { padding: 16px; }
            .plan-addons .btn { width: 100%; }
            .cta-box { padding: 32px 18px; }
            .wa-float { bottom: 14px; left: 14px; padding: 9px 14px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    {{-- Sticky Navbar --}}
    <header class="nav">
        <div class="container nav-inner">
            <a href="{{ route('landing') }}" class="nav-logo">
                <img src="{{ asset('images/logo-dark.png') }}" alt="TOURISM-SALES">
            </a>

            <nav class="nav-links">
                <a href="#features">{{ __('ui.landing_nav_features') }}</a>
                <a href="#pricing">{{ __('ui.landing_nav_pricing') }}</a>
                <a href="#faq">{{ __('ui.landing_nav_faq') }}</a>
            </nav>

            <div class="nav-actions">
                <div class="lang-group">
                    @foreach (['he' => 'עב', 'ar' => 'عر', 'en' => 'EN'] as $code => $label)
                        <form method="POST" action="{{ route('locale.switch', $code) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="lang-btn {{ $locale === $code ? 'active' : '' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
                <a href="/agent/login" class="btn btn-outline-light">{{ __('ui.login_btn') }}</a>
                <a href="{{ route('register.show') }}" class="btn btn-primary nav-cta-trial">{{ __('ui.start_free_trial_short') }}</a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <div class="hero-badge">✦ {{ __('ui.landing_hero_badge') }}</div>
                <h1>{{ __('ui.landing_hero_title_1') }} <span>{{ __('ui.landing_hero_title_highlight') }}</span></h1>
                <p>{{ __('ui.landing_hero_desc') }}</p>

                <div class="hero-ctas">
                    <a href="{{ route('register.show') }}" class="btn btn-primary btn-lg">
                        {{ __('ui.start_14_day_trial') }}
                    </a>
                    <a href="#pricing" class="btn btn-outline-light btn-lg">
                        {{ __('ui.view_packages') }}
                    </a>
                </div>

                <div class="hero-trust">
                    <span><b>✓</b> {{ __('ui.no_credit_card_required') }}</span>
                    <span><b>✓</b> {{ __('ui.instant_setup') }}</span>
                    <span><b>✓</b> {{ __('ui.he_ar_en_support') }}</span>
                </div>
            </div>

            <div class="mockup">
                <div class="mockup-bar">
                    <div class="mockup-dots">
                        <i style="background:#f43f5e;"></i>
                        <i style="background:#f59e0b;"></i>
                        <i style="background:#10b981;"></i>
                    </div>
                    <span style="font-size: 0.78rem; color: #94a3b8; font-weight: 700;">TOURISM-SALES CRM</span>
                </div>
                <div class="mockup-kpis">
                    <div class="mockup-kpi">
                        <small>{{ __('ui.active_trips') }}</small>
                        <strong>148</strong>
                    </div>
                    <div class="mockup-kpi" style="border-top-color:#10b981;">
                        <small>{{ __('ui.monthly_revenue') }}</small>
                        <strong style="color:#34d399;">₪184,500</strong>
                    </div>
                    <div class="mockup-kpi" style="border-top-color:#0ea5e9;">
                        <small>{{ __('ui.collection_rate') }}</small>
                        <strong style="color:#38bdf8;">94%</strong>
                    </div>
                </div>
                <div class="mockup-chart">
                    <span style="height: 42%;"></span>
                    <span style="height: 58%;"></span>
                    <span style="height: 51%;"></span>
                    <span style="height: 76%;"></span>
                    <span style="height: 84%;"></span>
                    <span style="height: 98%;"></span>
                </div>
                <div class="mockup-push">
                    <div class="mockup-push-icon">🔔</div>
                    <div>
                        <div style="font-size: 0.82rem; font-weight: 700; color: #ffffff;">{{ __('ui.landing_mockup_push_title') }}</div>
                        <div style="font-size: 0.74rem; color: #94a3b8;">{{ __('ui.landing_mockup_push_body') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="section">
        <div class="container">
            <div class="section-head">
                <span class="section-eyebrow">{{ __('ui.landing_features_eyebrow') }}</span>
                <h2>{{ __('ui.landing_features_title') }}</h2>
                <p>{{ __('ui.landing_features_desc') }}</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">✈</div>
                    <h3>{{ __('ui.feat_trips_title') }}</h3>
                    <p>{{ __('ui.feat_trips_desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📄</div>
                    <h3>{{ __('ui.feat_pdf_title') }}</h3>
                    <p>{{ __('ui.feat_pdf_desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>{{ __('ui.feat_team_title') }}</h3>
                    <p>{{ __('ui.feat_team_desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>{{ __('ui.feat_push_title') }}</h3>
                    <p>{{ __('ui.feat_push_desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>{{ __('ui.feat_messaging_title') }}</h3>
                    <p>{{ __('ui.feat_messaging_desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>{{ __('ui.feat_analytics_title') }}</h3>
                    <p>{{ __('ui.feat_analytics_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="section section-alt">
        <div class="container">
            <div class="section-head">
                <span class="section-eyebrow">{{ __('ui.plans_and_pricing') }}</span>
                <h2>{{ __('ui.landing_pricing_title') }}</h2>
                <p>{{ __('ui.landing_pricing_desc') }}</p>
            </div>

            <div class="billing-toggle-wrap">
                <div class="billing-toggle" role="group">
                    <button type="button" id="btnMonthly" class="billing-opt active" onclick="setBilling('monthly')">
                        {{ __('ui.billing_monthly') }}
                    </button>
                    <button type="button" id="btnYearly" class="billing-opt" onclick="setBilling('yearly')">
                        <span>{{ __('ui.billing_yearly') }}</span>
                        <span class="save-pill">{{ __('ui.save_12_percent') }}</span>
                    </button>
                </div>
            </div>

            <div class="pricing-grid">
                @foreach ($plans as $plan)
                    @php
                        $sym = $plan->getCurrencySymbol();
                        $monthly = (int) round((float) $plan->monthly_price);
                        $yearlyTotal = (int) round((float) $plan->yearly_price);
                        $yearlyPerMonth = (int) round($yearlyTotal / 12);
                        $channels = $plan->getAllowedChannels();
                        $hasSms = in_array('sms', $channels, true);
                    @endphp
                    <div class="plan-card {{ $plan->is_featured ? 'featured' : '' }}">
                        @if ($plan->is_featured)
                            <div class="plan-ribbon">{{ __('ui.most_popular') }}</div>
                        @endif

                        <h3 class="plan-name">{{ $plan->getLocalizedName($locale) }}</h3>
                        <p class="plan-desc">{{ $plan->getLocalizedDescription($locale) }}</p>

                        <div class="plan-price-row">
                            <span
                                class="plan-price js-plan-price"
                                data-monthly="{{ $sym }}{{ number_format($monthly) }}"
                                data-yearly="{{ $sym }}{{ number_format($yearlyPerMonth) }}"
                            >{{ $sym }}{{ number_format($monthly) }}</span>
                            <span class="plan-period">/ {{ __('ui.month_short') }} {{ __('ui.plus_vat') }}</span>
                        </div>
                        <div
                            class="plan-annual-note js-plan-note"
                            data-monthly="{{ __('ui.trial_days_included', ['days' => $plan->trial_days]) }}"
                            data-yearly="{{ __('ui.billed_yearly_total', ['total' => $sym . number_format($yearlyTotal)]) }}"
                        >{{ __('ui.trial_days_included', ['days' => $plan->trial_days]) }}</div>

                        <ul class="plan-list">
                            <li>
                                <span class="plan-check">✓</span>
                                <span>{{ __('ui.plan_feat_users', ['count' => $plan->included_users]) }}</span>
                            </li>
                            <li>
                                <span class="plan-check">✓</span>
                                <span>{{ __('ui.plan_feat_extra_user', ['price' => $sym . number_format((float) $plan->extra_user_price, 0)]) }}</span>
                            </li>
                            <li>
                                <span class="plan-check">✓</span>
                                <span>{{ __('ui.plan_feat_unlimited_crm') }}</span>
                            </li>
                            <li>
                                <span class="plan-check">✓</span>
                                <span>{{ __('ui.plan_feat_pdf_whatsapp') }}</span>
                            </li>
                            <li>
                                <span class="plan-check">✓</span>
                                <span>{{ __('ui.plan_feat_push_app') }}</span>
                            </li>
                            <li>
                                <span class="plan-check">{{ $plan->can_use_employee_reports ? '✓' : '•' }}</span>
                                <span style="{{ $plan->can_use_employee_reports ? '' : 'opacity:0.65;' }}">
                                    {{ $plan->can_use_employee_reports ? __('ui.plan_feat_emp_reports_yes') : __('ui.plan_feat_emp_reports_no') }}
                                </span>
                            </li>
                            <li>
                                <span class="plan-check">{{ $hasSms ? '✓' : '+' }}</span>
                                <span>{{ $hasSms ? __('ui.plan_feat_sms_included') : __('ui.plan_feat_sms_addon') }}</span>
                            </li>
                        </ul>

                        <a
                            href="{{ route('register.show', ['plan' => $plan->slug]) }}"
                            data-base-href="{{ route('register.show', ['plan' => $plan->slug]) }}"
                            class="btn js-plan-cta {{ $plan->is_featured ? 'btn-primary' : 'btn-dark' }}"
                            style="width: 100%; padding: 12px 16px;"
                        >
                            {{ __('ui.choose_plan_trial', ['days' => $plan->trial_days]) }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="plan-addons">
                <div>
                    <strong style="font-size: 0.95rem; color: #0f172a;">{{ __('ui.addons_banner_title') }}</strong>
                    <div style="font-size: 0.85rem; color: #78350f; margin-top: 2px;">{{ __('ui.addons_banner_desc') }}</div>
                </div>
                <a
                    href="https://api.whatsapp.com/send?phone={{ $contactPhoneIntl }}&text={{ rawurlencode(__('ui.wa_sales_prefill')) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-dark"
                >
                    {{ __('ui.talk_to_us_whatsapp') }} ({{ $contactPhoneDisplay }})
                </a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="section">
        <div class="container">
            <div class="section-head">
                <span class="section-eyebrow">{{ __('ui.landing_nav_faq') }}</span>
                <h2>{{ __('ui.landing_faq_title') }}</h2>
            </div>

            <div class="faq-list">
                <details class="faq-item" open>
                    <summary>{{ __('ui.faq_q1') }} <span>+</span></summary>
                    <p>{{ __('ui.faq_a1') }}</p>
                </details>
                <details class="faq-item">
                    <summary>{{ __('ui.faq_q2') }} <span>+</span></summary>
                    <p>{{ __('ui.faq_a2') }}</p>
                </details>
                <details class="faq-item">
                    <summary>{{ __('ui.faq_q3') }} <span>+</span></summary>
                    <p>{{ __('ui.faq_a3') }}</p>
                </details>
                <details class="faq-item">
                    <summary>{{ __('ui.faq_q4') }} <span>+</span></summary>
                    <p>{{ __('ui.faq_a4') }}</p>
                </details>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="section" style="padding-top: 10px;">
        <div class="container">
            <div class="cta-box">
                <h2>{{ __('ui.landing_final_cta_title') }}</h2>
                <p>{{ __('ui.landing_final_cta_desc') }}</p>
                <div class="hero-ctas" style="justify-content: center;">
                    <a href="{{ route('register.show') }}" class="btn btn-primary btn-lg">{{ __('ui.start_14_day_trial') }}</a>
                    <a href="tel:+{{ $contactPhoneIntl }}" class="btn btn-outline-light btn-lg" dir="ltr">📞 {{ $contactPhoneDisplay }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="footer">
        <div class="container footer-inner">
            <div style="display:flex; align-items:center; gap:12px;">
                <img src="{{ asset('images/logo-dark.png') }}" alt="TOURISM-SALES" style="height:28px;">
                <span>© {{ date('Y') }} TOURISM-SALES by ASAF DIGITAL</span>
            </div>
            <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
                <a href="/agent/login">{{ __('ui.agent_login_link') }}</a>
                <a href="{{ route('register.show') }}">{{ __('ui.start_free_trial_short') }}</a>
                <a href="{{ route('legal.privacy') }}">{{ __('ui.privacy_policy') }}</a>
                <a href="{{ route('legal.accessibility') }}">{{ __('ui.accessibility_statement') }}</a>
                <a href="{{ route('legal.terms') }}">{{ __('ui.terms_of_service') }}</a>
                <a href="https://api.whatsapp.com/send?phone={{ $contactPhoneIntl }}" target="_blank" rel="noopener" dir="ltr">{{ $contactPhoneDisplay }}</a>
            </div>
        </div>
    </footer>

    <a
        href="https://api.whatsapp.com/send?phone={{ $contactPhoneIntl }}&text={{ rawurlencode(__('ui.wa_sales_prefill')) }}"
        target="_blank"
        rel="noopener"
        class="wa-float"
    >
        <span>💬</span>
        <span>{{ __('ui.whatsapp_contact_float') }}</span>
    </a>

    @include('components.site-compliance')

    <script>
        function setBilling(mode) {
            document.getElementById('btnMonthly').classList.toggle('active', mode === 'monthly');
            document.getElementById('btnYearly').classList.toggle('active', mode === 'yearly');

            document.querySelectorAll('.js-plan-price').forEach(el => {
                el.textContent = mode === 'yearly' ? el.dataset.yearly : el.dataset.monthly;
            });

            document.querySelectorAll('.js-plan-note').forEach(el => {
                el.textContent = mode === 'yearly' ? el.dataset.yearly : el.dataset.monthly;
            });

            document.querySelectorAll('.js-plan-cta').forEach(el => {
                const base = el.dataset.baseHref;
                el.setAttribute('href', base + (base.includes('?') ? '&' : '?') + 'billing=' + mode);
            });
        }
    </script>
</body>
</html>
