@php
    $livewire ??= null;
    $currentLocale = app()->getLocale();
    $isRtl = in_array($currentLocale, ['he', 'ar'], true);
    $locales = [
        'he' => 'עברית',
        'ar' => 'العربية',
        'en' => 'English',
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <style>
        .ts-login-wrapper {
            direction: ltr;
            display: flex;
            min-height: 100vh;
            width: 100%;
            font-family: 'Open Sans', sans-serif;
            background-color: #ffffff;
        }

        html.dark .ts-login-wrapper {
            background-color: #09090b;
        }

        .ts-login-form-pane {
            flex: 1 1 50%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.75rem 2rem;
            min-height: 100vh;
            background-color: #ffffff;
        }

        html.dark .ts-login-form-pane {
            background-color: #09090b;
        }

        .ts-login-hero-pane {
            flex: 1 1 50%;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            background-color: #111827;
        }

        .ts-login-hero-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .ts-login-hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(15, 23, 42, 0.78) 0%,
                rgba(15, 23, 42, 0.18) 42%,
                rgba(15, 23, 42, 0.05) 100%
            );
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2.5rem;
        }

        .ts-lang-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: 100%;
        }

        .ts-lang-switcher {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem;
            border-radius: 0.625rem;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
        }

        html.dark .ts-lang-switcher {
            background-color: #18181b;
            border-color: #27272a;
        }

        .ts-lang-btn {
            border: none;
            background: transparent;
            color: #4b5563;
            font-size: 0.8125rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 0.4375rem;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        html.dark .ts-lang-btn {
            color: #a1a1aa;
        }

        .ts-lang-btn:hover {
            color: #111827;
        }

        html.dark .ts-lang-btn:hover {
            color: #ffffff;
        }

        .ts-lang-btn-active {
            background-color: #F59E0B;
            color: #ffffff !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
        }

        .ts-login-card-wrap {
            width: 100%;
            max-width: 26rem;
            margin: auto;
            padding: 2rem 0;
        }

        .ts-logo-light {
            display: block;
            height: 2.75rem;
            width: auto;
            margin-bottom: 1.75rem;
        }

        .ts-logo-dark {
            display: none;
            height: 2.75rem;
            width: auto;
            margin-bottom: 1.75rem;
        }

        html.dark .ts-logo-light {
            display: none;
        }

        html.dark .ts-logo-dark {
            display: block;
        }

        .ts-login-heading {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.5rem 0;
            line-height: 1.3;
        }

        html.dark .ts-login-heading {
            color: #f9fafb;
        }

        .ts-login-subheading {
            font-size: 0.9rem;
            color: #6b7280;
            margin: 0 0 1.75rem 0;
        }

        html.dark .ts-login-subheading {
            color: #9ca3af;
        }

        .ts-login-footer {
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        @media (max-width: 1023px) {
            .ts-login-wrapper {
                flex-direction: column-reverse;
            }

            .ts-login-hero-pane {
                flex: none;
                min-height: 13.5rem;
                height: 13.5rem;
                width: 100%;
            }

            .ts-login-hero-overlay {
                padding: 1.25rem 1.25rem 2.25rem 1.25rem;
                align-items: center;
                text-align: center;
                background: linear-gradient(
                    to top,
                    rgba(9, 9, 11, 0.88) 0%,
                    rgba(9, 9, 11, 0.35) 55%,
                    rgba(9, 9, 11, 0.1) 100%
                );
            }

            .ts-hero-logo {
                align-self: center !important;
                height: 2.25rem !important;
                margin-bottom: 0.4rem !important;
            }

            .ts-hero-tagline {
                font-size: 0.825rem !important;
                text-align: center;
            }

            .ts-login-form-pane {
                flex: 1 1 auto;
                min-height: auto;
                padding: 1.25rem 1.25rem 1.5rem 1.25rem;
                margin-top: -1.25rem;
                position: relative;
                z-index: 10;
                border-top-left-radius: 1.5rem;
                border-top-right-radius: 1.5rem;
                box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.2);
            }

            .ts-lang-bar {
                justify-content: center;
                margin-bottom: 1.25rem;
            }

            .ts-login-card-wrap {
                padding: 0.25rem 0 1.25rem 0;
            }

            .ts-logo-light,
            .ts-logo-dark,
            .ts-login-subheading {
                display: none !important;
            }

            .ts-login-heading {
                font-size: 1.35rem;
                text-align: center;
                margin-bottom: 1.25rem;
            }
        }
    </style>

    <div class="ts-login-wrapper">
        {{-- Left Pane: Login Form + Language Switcher --}}
        <div class="ts-login-form-pane" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
            <div class="ts-lang-bar">
                <div class="ts-lang-switcher" role="group" aria-label="{{ __('ui.language') }}">
                    @foreach ($locales as $code => $label)
                        <form method="POST" action="{{ route('locale.switch', $code) }}" style="margin: 0;">
                            @csrf
                            <button
                                type="submit"
                                class="ts-lang-btn {{ $currentLocale === $code ? 'ts-lang-btn-active' : '' }}"
                            >
                                {{ $label }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            <main class="ts-login-card-wrap">
                <img
                    src="{{ asset('images/logo-light.png') }}"
                    alt="{{ filament()->getBrandName() }}"
                    class="ts-logo-light"
                />
                <img
                    src="{{ asset('images/logo-dark.png') }}"
                    alt="{{ filament()->getBrandName() }}"
                    class="ts-logo-dark"
                />

                @if ($livewire && filled($heading = $livewire->getHeading()))
                    <h1 class="ts-login-heading">
                        {{ $heading }}
                    </h1>
                @endif

                <p class="ts-login-subheading">
                    {{ __('ui.login_tagline') }}
                </p>

                {{ $slot }}
            </main>

            <div class="ts-login-footer">
                &copy; {{ date('Y') }} TOURISM-SALES by ASAF DIGITAL
            </div>
        </div>

        {{-- Right Pane: Hero Image --}}
        <div class="ts-login-hero-pane">
            <img
                src="{{ asset('images/login-hero.jpg') }}"
                alt="TOURISM-SALES"
                class="ts-login-hero-img"
            />
            <div class="ts-login-hero-overlay" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                <img
                    src="{{ asset('images/logo-dark.png') }}"
                    alt="TOURISM-SALES"
                    class="ts-hero-logo"
                    style="height: 2.5rem; width: auto; align-self: flex-start; margin-bottom: 0.75rem;"
                />
                <p class="ts-hero-tagline" style="color: rgba(255, 255, 255, 0.92); font-size: 1rem; font-weight: 500; margin: 0; max-width: 28rem;">
                    {{ __('ui.login_tagline') }}
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
