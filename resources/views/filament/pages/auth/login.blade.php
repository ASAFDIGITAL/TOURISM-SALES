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
</div>
