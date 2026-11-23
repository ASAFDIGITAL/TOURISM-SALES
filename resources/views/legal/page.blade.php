<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.legal_' . $type . '_title') }} — TOURISM-SALES</title>
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
            @if ($locale === 'ar')
                font-family: 'Cairo', sans-serif;
            @else
                font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            @endif
            background: #f4f6fa;
            color: #0f172a;
            line-height: 1.75;
        }
        a { color: #d97706; }
        .top-nav {
            background: #0f172a;
            color: #ffffff;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .top-nav-inner {
            max-width: 960px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .top-nav img { height: 32px; display: block; }
        .top-links { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .lang-group {
            display: inline-flex;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
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
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
        }
        .lang-btn.active { background: #f59e0b; color: #0f172a; }
        .back-btn {
            color: #ffffff;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
        }
        .wrap { max-width: 920px; margin: 36px auto 80px; padding: 0 18px; }
        .legal-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: clamp(22px, 4vw, 44px);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);
        }
        .legal-card h1 { margin: 0 0 8px; font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 800; color: #0f172a; }
        .legal-meta { font-size: 0.82rem; color: #64748b; margin-bottom: 26px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
        .legal-card h2 { margin: 26px 0 8px; font-size: 1.12rem; font-weight: 800; color: #0f172a; }
        .legal-card p { margin: 0 0 12px; color: #334155; font-size: 0.94rem; }
        .legal-card ul { margin: 0 0 14px; padding-inline-start: 22px; color: #334155; font-size: 0.94rem; }
        .legal-card li { margin-bottom: 6px; }
        .contact-box {
            margin-top: 28px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 14px;
            padding: 18px 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header class="top-nav">
        <div class="top-nav-inner">
            <a href="{{ route('landing') }}"><img src="{{ asset('images/logo-dark.png') }}" alt="TOURISM-SALES"></a>
            <div class="top-links">
                <div class="lang-group">
                    @foreach (['he' => 'עב', 'ar' => 'عر', 'en' => 'EN'] as $code => $label)
                        <form method="POST" action="{{ route('locale.switch', $code) }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="lang-btn {{ $locale === $code ? 'active' : '' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
                <a href="{{ route('landing') }}" class="back-btn">{{ __('ui.back_to_home') }}</a>
            </div>
        </div>
    </header>

    <main class="wrap">
        <article class="legal-card">
            <h1>{{ __('ui.legal_' . $type . '_title') }}</h1>
            <div class="legal-meta">{{ __('ui.legal_last_updated') }}: {{ $updatedDate }} · TOURISM-SALES by ASAF DIGITAL</div>

            @if ($type === 'privacy')
                <h2>{{ __('ui.legal_privacy_h1') }}</h2>
                <p>{{ __('ui.legal_privacy_p1') }}</p>

                <h2>{{ __('ui.legal_privacy_h2') }}</h2>
                <ul>
                    <li>{{ __('ui.legal_privacy_l1') }}</li>
                    <li>{{ __('ui.legal_privacy_l2') }}</li>
                    <li>{{ __('ui.legal_privacy_l3') }}</li>
                    <li>{{ __('ui.legal_privacy_l4') }}</li>
                </ul>

                <h2>{{ __('ui.legal_privacy_h3') }}</h2>
                <p>{{ __('ui.legal_privacy_p3') }}</p>

                <h2>{{ __('ui.legal_privacy_h4') }}</h2>
                <p>{{ __('ui.legal_privacy_p4') }}</p>

                <h2>{{ __('ui.legal_privacy_h5') }}</h2>
                <p>{{ __('ui.legal_privacy_p5') }}</p>
            @elseif ($type === 'accessibility')
                <h2>{{ __('ui.legal_a11y_h1') }}</h2>
                <p>{{ __('ui.legal_a11y_p1') }}</p>

                <h2>{{ __('ui.legal_a11y_h2') }}</h2>
                <ul>
                    <li>{{ __('ui.legal_a11y_l1') }}</li>
                    <li>{{ __('ui.legal_a11y_l2') }}</li>
                    <li>{{ __('ui.legal_a11y_l3') }}</li>
                    <li>{{ __('ui.legal_a11y_l4') }}</li>
                    <li>{{ __('ui.legal_a11y_l5') }}</li>
                </ul>

                <h2>{{ __('ui.legal_a11y_h3') }}</h2>
                <p>{{ __('ui.legal_a11y_p3') }}</p>

                <h2>{{ __('ui.legal_a11y_h4') }}</h2>
                <p>{{ __('ui.legal_a11y_p4') }}</p>
            @else
                <h2>{{ __('ui.legal_terms_h1') }}</h2>
                <p>{{ __('ui.legal_terms_p1') }}</p>

                <h2>{{ __('ui.legal_terms_h2') }}</h2>
                <p>{{ __('ui.legal_terms_p2') }}</p>

                <h2>{{ __('ui.legal_terms_h3') }}</h2>
                <p>{{ __('ui.legal_terms_p3') }}</p>

                <h2>{{ __('ui.legal_terms_h4') }}</h2>
                <p>{{ __('ui.legal_terms_p4') }}</p>
            @endif

            <div class="contact-box">
                <strong>{{ __('ui.legal_contact_heading') }}</strong><br>
                {{ __('ui.phone') }} / WhatsApp: <a href="https://api.whatsapp.com/send?phone={{ $contactPhoneIntl }}" target="_blank" rel="noopener" dir="ltr">{{ $contactPhoneDisplay }}</a><br>
                {{ __('ui.email') }}: <a href="mailto:{{ $contactEmail }}" dir="ltr">{{ $contactEmail }}</a>
            </div>
        </article>
    </main>

    @include('components.site-compliance')
</body>
</html>
