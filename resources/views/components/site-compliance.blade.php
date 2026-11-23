@php
    $cLocale = in_array(app()->getLocale(), ['he', 'ar', 'en'], true) ? app()->getLocale() : 'he';
    $cIsRtl = in_array($cLocale, ['he', 'ar'], true);
@endphp

<style>
    /* Accessibility Modifiers */
    html.ts-a11y-grayscale { filter: grayscale(100%); }
    html.ts-a11y-contrast body { background: #000000 !important; color: #ffff00 !important; }
    html.ts-a11y-contrast section,
    html.ts-a11y-contrast .feature-card,
    html.ts-a11y-contrast .plan-card,
    html.ts-a11y-contrast .faq-item,
    html.ts-a11y-contrast .legal-card,
    html.ts-a11y-contrast .form-pane {
        background: #000000 !important;
        color: #ffff00 !important;
        border-color: #ffff00 !important;
    }
    html.ts-a11y-contrast a,
    html.ts-a11y-contrast h1,
    html.ts-a11y-contrast h2,
    html.ts-a11y-contrast h3,
    html.ts-a11y-contrast p,
    html.ts-a11y-contrast li,
    html.ts-a11y-contrast label {
        color: #ffff00 !important;
    }
    html.ts-a11y-links a {
        text-decoration: underline !important;
        outline: 2px solid #f59e0b !important;
        outline-offset: 2px;
    }
    html.ts-a11y-readable,
    html.ts-a11y-readable body,
    html.ts-a11y-readable * {
        font-family: Arial, Helvetica, sans-serif !important;
        letter-spacing: 0.02em !important;
        line-height: 1.75 !important;
    }
    html.ts-a11y-no-anim *,
    html.ts-a11y-no-anim *::before,
    html.ts-a11y-no-anim *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
    }

    /* Accessibility Floating Trigger (Bottom-Right) */
    .ts-a11y-trigger {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 60;
        width: 48px;
        height: 48px;
        border-radius: 9999px;
        border: 2px solid #ffffff;
        background: #1d4ed8;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 10px 25px -5px rgba(29, 78, 216, 0.55);
        transition: transform 0.16s ease, background 0.16s ease;
    }
    .ts-a11y-trigger:hover { transform: scale(1.06); background: #1e40af; }

    .ts-a11y-panel {
        position: fixed;
        bottom: 78px;
        right: 20px;
        z-index: 65;
        width: 310px;
        max-width: calc(100vw - 24px);
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #cbd5e1;
        border-radius: 16px;
        box-shadow: 0 22px 48px -12px rgba(15, 23, 42, 0.35);
        overflow: hidden;
        display: none;
    }
    .ts-a11y-panel.is-open { display: block; }
    .ts-a11y-head {
        background: #0f172a;
        color: #ffffff;
        padding: 12px 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 700;
        font-size: 0.9rem;
    }
    .ts-a11y-close {
        background: rgba(255, 255, 255, 0.14);
        border: none;
        color: #ffffff;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        line-height: 1;
    }
    .ts-a11y-body {
        padding: 12px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .ts-a11y-opt {
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #0f172a;
        border-radius: 10px;
        padding: 9px 8px;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        text-align: center;
        font-family: inherit;
        transition: all 0.14s ease;
    }
    .ts-a11y-opt:hover { border-color: #1d4ed8; background: #eff6ff; }
    .ts-a11y-opt.is-active {
        background: #1d4ed8;
        color: #ffffff;
        border-color: #1d4ed8;
    }
    .ts-a11y-foot {
        padding: 10px 12px 12px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        font-size: 0.78rem;
    }
    .ts-a11y-reset {
        background: #f1f5f9;
        color: #334155;
        border: none;
        border-radius: 8px;
        padding: 6px 10px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }
    .ts-a11y-statement-link {
        color: #1d4ed8;
        font-weight: 700;
        text-decoration: underline;
    }

    /* Cookie Consent Banner */
    .ts-cookie-banner {
        position: fixed;
        bottom: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 58;
        width: 680px;
        max-width: calc(100vw - 24px);
        background: rgba(15, 23, 42, 0.97);
        color: #f8fafc;
        border: 1px solid rgba(245, 158, 11, 0.45);
        border-radius: 16px;
        padding: 14px 18px;
        box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }
    .ts-cookie-banner.is-visible { display: flex; }
    .ts-cookie-text {
        flex: 1 1 320px;
        font-size: 0.82rem;
        color: #e2e8f0;
        line-height: 1.5;
    }
    .ts-cookie-text a { color: #fbbf24; text-decoration: underline; font-weight: 700; }
    .ts-cookie-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }
    .ts-cookie-btn {
        border: none;
        border-radius: 9px;
        padding: 8px 14px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }
    .ts-cookie-accept { background: #f59e0b; color: #0f172a; }
    .ts-cookie-essential { background: rgba(255, 255, 255, 0.12); color: #f8fafc; }

    @media (max-width: 640px) {
        .ts-a11y-trigger { bottom: 14px; right: 14px; width: 44px; height: 44px; }
        .ts-a11y-panel { bottom: 68px; right: 12px; }
        .ts-cookie-banner { bottom: 70px; padding: 12px 14px; }
        .ts-cookie-actions { width: 100%; }
        .ts-cookie-btn { flex: 1; }
    }
</style>

{{-- Accessibility Floating Button --}}
<button
    type="button"
    id="tsA11yTrigger"
    class="ts-a11y-trigger"
    aria-label="{{ __('ui.a11y_menu_title') }}"
    aria-expanded="false"
    aria-controls="tsA11yPanel"
    onclick="tsToggleA11yPanel()"
>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm7 7h-5v13h-2v-6h-2v6H8V9H3V7h16v2Z"/>
    </svg>
</button>

{{-- Accessibility Panel --}}
<aside id="tsA11yPanel" class="ts-a11y-panel" dir="{{ $cIsRtl ? 'rtl' : 'ltr' }}" aria-label="{{ __('ui.a11y_menu_title') }}">
    <div class="ts-a11y-head">
        <span>♿ {{ __('ui.a11y_menu_title') }}</span>
        <button type="button" class="ts-a11y-close" onclick="tsToggleA11yPanel(false)" aria-label="Close">×</button>
    </div>
    <div class="ts-a11y-body">
        <button type="button" class="ts-a11y-opt" onclick="tsA11yFont(10)">A+ {{ __('ui.a11y_increase_text') }}</button>
        <button type="button" class="ts-a11y-opt" onclick="tsA11yFont(-10)">A- {{ __('ui.a11y_decrease_text') }}</button>
        <button type="button" class="ts-a11y-opt" data-a11y="contrast" onclick="tsA11yToggle('contrast')">◐ {{ __('ui.a11y_high_contrast') }}</button>
        <button type="button" class="ts-a11y-opt" data-a11y="grayscale" onclick="tsA11yToggle('grayscale')">◑ {{ __('ui.a11y_grayscale') }}</button>
        <button type="button" class="ts-a11y-opt" data-a11y="links" onclick="tsA11yToggle('links')">🔗 {{ __('ui.a11y_highlight_links') }}</button>
        <button type="button" class="ts-a11y-opt" data-a11y="readable" onclick="tsA11yToggle('readable')">Aa {{ __('ui.a11y_readable_font') }}</button>
        <button type="button" class="ts-a11y-opt" style="grid-column: 1 / -1;" data-a11y="noAnim" onclick="tsA11yToggle('noAnim')">⏸ {{ __('ui.a11y_stop_animations') }}</button>
    </div>
    <div class="ts-a11y-foot">
        <a href="{{ route('legal.accessibility') }}" class="ts-a11y-statement-link">{{ __('ui.accessibility_statement') }}</a>
        <button type="button" class="ts-a11y-reset" onclick="tsA11yReset()">{{ __('ui.reset') }}</button>
    </div>
</aside>

{{-- Cookie Consent Banner --}}
<div id="tsCookieBanner" class="ts-cookie-banner" dir="{{ $cIsRtl ? 'rtl' : 'ltr' }}" role="dialog" aria-label="{{ __('ui.cookies_title') }}">
    <div class="ts-cookie-text">
        <strong>🍪 {{ __('ui.cookies_title') }}:</strong>
        {{ __('ui.cookies_banner_text') }}
        <a href="{{ route('legal.privacy') }}">{{ __('ui.privacy_policy') }}</a>
    </div>
    <div class="ts-cookie-actions">
        <button type="button" class="ts-cookie-btn ts-cookie-essential" onclick="tsSaveCookieConsent('essential')">{{ __('ui.cookies_essential_only') }}</button>
        <button type="button" class="ts-cookie-btn ts-cookie-accept" onclick="tsSaveCookieConsent('all')">{{ __('ui.cookies_accept_all') }}</button>
    </div>
</div>

<script>
    (function () {
        const A11Y_KEY = 'ts_a11y_v1';
        const COOKIE_KEY = 'ts_cookie_consent_v1';

        function loadA11y() {
            try {
                return JSON.parse(localStorage.getItem(A11Y_KEY) || '{}') || {};
            } catch (e) {
                return {};
            }
        }

        function applyA11y(state) {
            const root = document.documentElement;
            const size = state.fontSize || 100;
            root.style.fontSize = size === 100 ? '' : (size + '%');
            root.classList.toggle('ts-a11y-contrast', Boolean(state.contrast));
            root.classList.toggle('ts-a11y-grayscale', Boolean(state.grayscale));
            root.classList.toggle('ts-a11y-links', Boolean(state.links));
            root.classList.toggle('ts-a11y-readable', Boolean(state.readable));
            root.classList.toggle('ts-a11y-no-anim', Boolean(state.noAnim));

            document.querySelectorAll('[data-a11y]').forEach(btn => {
                btn.classList.toggle('is-active', Boolean(state[btn.dataset.a11y]));
            });
        }

        window.tsToggleA11yPanel = function (force) {
            const panel = document.getElementById('tsA11yPanel');
            const trigger = document.getElementById('tsA11yTrigger');
            if (!panel) return;
            const open = typeof force === 'boolean' ? force : !panel.classList.contains('is-open');
            panel.classList.toggle('is-open', open);
            if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        window.tsA11yToggle = function (key) {
            const state = loadA11y();
            state[key] = !state[key];
            localStorage.setItem(A11Y_KEY, JSON.stringify(state));
            applyA11y(state);
        };

        window.tsA11yFont = function (delta) {
            const state = loadA11y();
            const next = Math.min(140, Math.max(90, (state.fontSize || 100) + delta));
            state.fontSize = next;
            localStorage.setItem(A11Y_KEY, JSON.stringify(state));
            applyA11y(state);
        };

        window.tsA11yReset = function () {
            localStorage.removeItem(A11Y_KEY);
            applyA11y({});
        };

        window.tsSaveCookieConsent = function (choice) {
            try {
                localStorage.setItem(COOKIE_KEY, JSON.stringify({ choice: choice, at: new Date().toISOString() }));
            } catch (e) {}
            const banner = document.getElementById('tsCookieBanner');
            if (banner) banner.classList.remove('is-visible');
        };

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') window.tsToggleA11yPanel(false);
        });

        applyA11y(loadA11y());

        try {
            if (!localStorage.getItem(COOKIE_KEY)) {
                const banner = document.getElementById('tsCookieBanner');
                if (banner) banner.classList.add('is-visible');
            }
        } catch (e) {}
    })();
</script>
