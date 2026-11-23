<div>
    <style>
        .ts-method-tabs {
            display: flex;
            gap: 0.375rem;
            padding: 0.25rem;
            border-radius: 0.625rem;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        html.dark .ts-method-tabs {
            background-color: #18181b;
            border-color: #27272a;
        }

        .ts-method-tab {
            flex: 1 1 50%;
            border: none;
            background: transparent;
            color: #4b5563;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.55rem 0.75rem;
            border-radius: 0.45rem;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
            text-align: center;
        }

        html.dark .ts-method-tab {
            color: #a1a1aa;
        }

        .ts-method-tab-active {
            background-color: #ffffff;
            color: #111827 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        html.dark .ts-method-tab-active {
            background-color: #27272a;
            color: #ffffff !important;
        }

        .ts-phone-field {
            margin-bottom: 1rem;
        }

        .ts-phone-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.4rem;
        }

        html.dark .ts-phone-label {
            color: #f3f4f6;
        }

        .ts-phone-input {
            width: 100%;
            box-sizing: border-box;
            padding: 0.6rem 0.85rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            color: #111827;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        html.dark .ts-phone-input {
            background-color: #18181b;
            border-color: #3f3f46;
            color: #ffffff;
        }

        .ts-phone-input:focus {
            border-color: #F59E0B;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.28);
        }

        .ts-otp-input {
            text-align: center;
            letter-spacing: 0.4em;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .ts-phone-error {
            color: #dc2626;
            font-size: 0.8125rem;
            margin-top: 0.35rem;
        }

        .ts-phone-info {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            border-radius: 0.5rem;
            padding: 0.65rem 0.85rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        html.dark .ts-phone-info {
            background-color: rgba(217, 119, 6, 0.14);
            border-color: rgba(217, 119, 6, 0.35);
            color: #fcd34d;
        }

        .ts-primary-btn {
            width: 100%;
            border: none;
            border-radius: 0.5rem;
            background-color: #F59E0B;
            color: #ffffff;
            font-size: 0.925rem;
            font-weight: 600;
            padding: 0.65rem 1rem;
            cursor: pointer;
            font-family: inherit;
            transition: background-color 0.15s ease;
        }

        .ts-primary-btn:hover {
            background-color: #d97706;
        }

        .ts-primary-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .ts-secondary-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.85rem;
            gap: 0.5rem;
        }

        .ts-link-btn {
            border: none;
            background: transparent;
            color: #F59E0B;
            font-size: 0.825rem;
            font-weight: 600;
            cursor: pointer;
            padding: 0.25rem 0;
            font-family: inherit;
        }

        .ts-link-btn:hover {
            text-decoration: underline;
        }
    </style>

    {{-- Login Method Switcher --}}
    <div class="ts-method-tabs" role="tablist">
        <button
            type="button"
            wire:click="switchLoginMethod('email')"
            class="ts-method-tab {{ $loginMethod === 'email' ? 'ts-method-tab-active' : '' }}"
        >
            {{ __('ui.login_with_email') }}
        </button>
        <button
            type="button"
            wire:click="switchLoginMethod('phone')"
            class="ts-method-tab {{ $loginMethod === 'phone' ? 'ts-method-tab-active' : '' }}"
        >
            {{ __('ui.login_with_phone') }}
        </button>
    </div>

    @if ($loginMethod === 'email')
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

        <x-filament-panels::form id="form" wire:submit="authenticate">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
    @else
        @if (!$otpSent)
            <form wire:submit="sendOtp">
                <div class="ts-phone-field">
                    <label for="login-phone" class="ts-phone-label">
                        {{ __('ui.phone') }}
                    </label>
                    <input
                        id="login-phone"
                        type="tel"
                        dir="ltr"
                        wire:model="phone"
                        placeholder="{{ __('ui.phone_placeholder') }}"
                        autocomplete="tel"
                        required
                        class="ts-phone-input"
                    />
                    @error('phone')
                        <div class="ts-phone-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" class="ts-primary-btn">
                    <span wire:loading.remove wire:target="sendOtp">{{ __('ui.send_otp') }}</span>
                    <span wire:loading wire:target="sendOtp">...</span>
                </button>
            </form>
        @else
            <div class="ts-phone-info">
                {{ __('ui.otp_sent_to', ['phone' => $maskedPhone]) }}
            </div>

            <form wire:submit="verifyOtp">
                <div class="ts-phone-field">
                    <label for="login-otp" class="ts-phone-label">
                        {{ __('ui.otp_code') }}
                    </label>
                    <input
                        id="login-otp"
                        type="text"
                        dir="ltr"
                        inputmode="numeric"
                        maxlength="6"
                        wire:model="otpCode"
                        placeholder="000000"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        class="ts-phone-input ts-otp-input"
                    />
                    @error('otpCode')
                        <div class="ts-phone-error">{{ $message }}</div>
                    @enderror
                    @error('phone')
                        <div class="ts-phone-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" class="ts-primary-btn">
                    <span wire:loading.remove wire:target="verifyOtp">{{ __('ui.verify_and_login') }}</span>
                    <span wire:loading wire:target="verifyOtp">...</span>
                </button>

                <div class="ts-secondary-links">
                    <button type="button" wire:click="sendOtp" class="ts-link-btn">
                        {{ __('ui.resend_otp') }}
                    </button>
                    <button type="button" wire:click="resetPhoneLogin" class="ts-link-btn">
                        {{ __('ui.change_phone') }}
                    </button>
                </div>
            </form>
        @endif
    @endif

    <div style="margin-top: 1.1rem;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:0.85rem; color:#94a3b8; font-size:0.78rem;">
            <span style="flex:1; height:1px; background:rgba(148,163,184,0.3);"></span>
            <span>{{ __('ui.or_divider') }}</span>
            <span style="flex:1; height:1px; background:rgba(148,163,184,0.3);"></span>
        </div>

        <a
            href="{{ route('auth.google.redirect') }}"
            style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:0.65rem 1rem; border-radius:0.55rem; border:1px solid #cbd5e1; background:#ffffff; color:#0f172a; font-size:0.9rem; font-weight:700; text-decoration:none; box-shadow:0 1px 2px rgba(15,23,42,0.06);"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.745 12.27c0-.79-.07-1.54-.19-2.27h-11.3v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
                <path fill="#34A853" d="M12.255 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.515 21.3 7.565 24 12.255 24z"/>
                <path fill="#FBBC05" d="M5.525 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/>
                <path fill="#EA4335" d="M12.255 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C18.205 1.19 15.495 0 12.255 0c-4.69 0-8.74 2.7-10.71 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
            </svg>
            <span>{{ __('ui.continue_with_google') }}</span>
        </a>
    </div>

    <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid rgba(148, 163, 184, 0.25); text-align: center; font-size: 0.85rem;">
        <span style="opacity: 0.75;">{{ __('ui.no_account_yet_prompt') }}</span>
        <a href="{{ route('register.show') }}" style="color: #F59E0B; font-weight: 700; text-decoration: none; margin-inline-start: 4px;">
            {{ __('ui.start_14_day_trial') }}
        </a>
    </div>
</div>
