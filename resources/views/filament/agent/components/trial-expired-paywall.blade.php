@php
    $user = auth()->user();
    $tenant = $user && ! $user->isSuperAdmin() ? $user->getActiveTenant()?->load('plan') : null;
    $showPaywall = $tenant !== null && $tenant->isTrialExpired();
@endphp

@if ($showPaywall)
    @php
        \App\Models\Plan::ensureDefaultPlansExist();
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get();
        $payPlus = app(\App\Services\PayPlusService::class);
        $payPlusConfigured = $payPlus->isConfigured();
        $vatRate = $payPlus->getVatRate();
        $isOwner = $user->isAgencyOwner();
        $locale = app()->getLocale();
        $isRtl = in_array($locale, ['he', 'ar'], true);
    @endphp

    <style>
        html, body {
            overflow: hidden !important;
        }
        #tsTrialPaywallModal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            overflow-y: auto;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .ts-paywall-card {
            width: 100%;
            max-width: 960px;
            max-height: 92vh;
            overflow-y: auto;
            background: #ffffff;
            color: #0f172a;
            border-radius: 22px;
            box-shadow: 0 32px 80px -16px rgba(0, 0, 0, 0.65);
            border: 1px solid rgba(245, 158, 11, 0.35);
            overflow-x: hidden;
        }
        html.dark .ts-paywall-card {
            background: #0f172a;
            color: #f8fafc;
            border-color: rgba(245, 158, 11, 0.4);
        }
        .ts-paywall-head {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 26px 28px;
            border-bottom: 3px solid #f59e0b;
            text-align: center;
        }
        .ts-paywall-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.45);
            color: #fbbf24;
            font-size: 0.78rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .ts-paywall-head h2 {
            margin: 0;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.3;
        }
        .ts-paywall-head p {
            margin: 8px auto 0;
            max-width: 620px;
            font-size: 0.9rem;
            color: #cbd5e1;
        }
        .ts-paywall-body {
            padding: 24px 26px 22px;
        }
        .ts-paywall-toggle-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .ts-paywall-toggle {
            display: inline-flex;
            gap: 6px;
            padding: 4px;
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.22);
        }
        .ts-paywall-toggle button {
            border: none;
            padding: 8px 16px;
            border-radius: 9px;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            background: transparent;
            color: inherit;
        }
        .ts-paywall-toggle button.is-active {
            background: #0f172a;
            color: #fbbf24;
        }
        html.dark .ts-paywall-toggle button.is-active {
            background: #f59e0b;
            color: #0f172a;
        }
        .ts-paywall-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .ts-paywall-plan {
            border-radius: 16px;
            padding: 20px;
            border: 1.5px solid rgba(148, 163, 184, 0.32);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            background: rgba(148, 163, 184, 0.05);
        }
        .ts-paywall-plan.is-featured {
            border-color: #f59e0b;
            box-shadow: 0 12px 30px -14px rgba(245, 158, 11, 0.45);
        }
        .ts-paywall-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 11px 14px;
            border-radius: 11px;
            border: none;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #0f172a;
            font-size: 0.88rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 8px 18px -6px rgba(245, 158, 11, 0.55);
        }
        .ts-paywall-cta-dark {
            background: #0f172a;
            color: #fbbf24;
            box-shadow: none;
        }
        html.dark .ts-paywall-cta-dark {
            background: #1e293b;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .ts-paywall-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 26px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            font-size: 0.84rem;
        }
        @media (max-width: 820px) {
            .ts-paywall-grid {
                grid-template-columns: 1fr;
            }
            .ts-paywall-head {
                padding: 20px 18px;
            }
            .ts-paywall-head h2 {
                font-size: 1.3rem;
            }
            .ts-paywall-body {
                padding: 18px 16px;
            }
        }
    </style>

    <div id="tsTrialPaywallModal" role="dialog" aria-modal="true" aria-labelledby="tsPaywallTitle">
        <div class="ts-paywall-card">
            <div class="ts-paywall-head">
                <div class="ts-paywall-pill">🔒 {{ __('ui.trial_paywall_badge') }}</div>
                <h2 id="tsPaywallTitle">{{ __('ui.trial_paywall_title') }}</h2>
                <p>{{ $isOwner ? __('ui.trial_paywall_desc_owner') : __('ui.trial_paywall_desc_staff') }}</p>
            </div>

            <div class="ts-paywall-body">
                @if ($isOwner)
                    <div class="ts-paywall-toggle-wrap">
                        <div class="ts-paywall-toggle">
                            <button type="button" id="tsPwMonthlyBtn" class="is-active" onclick="tsSetPaywallCycle('monthly')">
                                {{ __('ui.billing_monthly') }}
                            </button>
                            <button type="button" id="tsPwYearlyBtn" onclick="tsSetPaywallCycle('yearly')">
                                {{ __('ui.billing_yearly') }} ({{ __('ui.save_12_percent') }})
                            </button>
                        </div>
                    </div>

                    <div class="ts-paywall-grid">
                        @foreach ($plans as $plan)
                            @php
                                $sym = $plan->getCurrencySymbol();
                                $monthlyTotals = $payPlus->calculateTotals($plan, 0, 'monthly');
                                $yearlyTotals = $payPlus->calculateTotals($plan, 0, 'yearly');
                                $isCurrent = $tenant->plan_id === $plan->id;
                                $upayMonthly = $plan->getUpayUrl('monthly');
                                $upayYearly = $plan->getUpayUrl('yearly');
                                $waMsg = rawurlencode('שלום, תקופת הניסיון הסתיימה עבור ' . $tenant->name . ' ואני רוצה להפעיל חבילת ' . $plan->getLocalizedName('he'));
                                $waUrl = 'https://api.whatsapp.com/send?phone=972502311554&text=' . $waMsg;
                            @endphp

                            <div class="ts-paywall-plan {{ ($isCurrent || $plan->is_featured) ? 'is-featured' : '' }}">
                                <div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                                        <strong style="font-size:1.05rem;">{{ $plan->getLocalizedName($locale) }}</strong>
                                        @if ($isCurrent)
                                            <span class="ts-support-badge ts-badge-closed">{{ __('ui.current_plan_badge') }}</span>
                                        @elseif ($plan->is_featured)
                                            <span class="ts-support-badge ts-badge-waiting_agent">{{ __('ui.most_popular') }}</span>
                                        @endif
                                    </div>

                                    {{-- Monthly Price View --}}
                                    <div class="ts-pw-monthly" style="margin-top:10px;">
                                        <div>
                                            <span style="font-size:1.65rem; font-weight:800;">{{ $sym }}{{ number_format($monthlyTotals['subtotal'], 0) }}</span>
                                            <span style="font-size:0.78rem; color:#64748b;">/ {{ __('ui.month_short') }} {{ __('ui.plus_vat') }}</span>
                                        </div>
                                        <div style="font-size:0.76rem; color:#10b981; font-weight:700; margin-top:2px;">
                                            {{ __('ui.total_including_vat', ['total' => $sym . number_format($monthlyTotals['total'], 2), 'vat' => $vatRate]) }}
                                        </div>
                                    </div>

                                    {{-- Yearly Price View --}}
                                    <div class="ts-pw-yearly" style="display:none; margin-top:10px;">
                                        <div>
                                            <span style="font-size:1.65rem; font-weight:800;">{{ $sym }}{{ number_format($yearlyTotals['subtotal'], 0) }}</span>
                                            <span style="font-size:0.78rem; color:#64748b;">/ {{ __('ui.year_short') }} {{ __('ui.plus_vat') }}</span>
                                        </div>
                                        <div style="font-size:0.76rem; color:#10b981; font-weight:700; margin-top:2px;">
                                            {{ __('ui.total_including_vat', ['total' => $sym . number_format($yearlyTotals['total'], 2), 'vat' => $vatRate]) }}
                                        </div>
                                    </div>

                                    <div style="margin-top:10px; font-size:0.8rem; color:#64748b; display:flex; flex-direction:column; gap:4px;">
                                        <div>✓ {{ __('ui.plan_feat_users', ['count' => $plan->included_users]) }}</div>
                                        @if ($plan->monthly_email_limit !== null)
                                            <div>✓ {{ __('ui.plan_feat_monthly_emails', ['count' => $plan->monthly_email_limit]) }}</div>
                                        @endif
                                        @if ($plan->monthly_sms_limit !== null)
                                            <div>✓ {{ __('ui.plan_feat_monthly_sms', ['count' => $plan->monthly_sms_limit]) }}</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Monthly CTA --}}
                                <div class="ts-pw-monthly">
                                    @if ($payPlusConfigured)
                                        <form method="POST" action="{{ route('payplus.checkout') }}">
                                            @csrf
                                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                            <input type="hidden" name="cycle" value="monthly">
                                            <input type="hidden" name="extra_seats" value="0">
                                            <button type="submit" class="ts-paywall-cta">💳 {{ __('ui.payplus_start_standing_order_btn') }}</button>
                                        </form>
                                    @elseif ($upayMonthly)
                                        <a href="{{ $upayMonthly }}" target="_blank" rel="noopener" class="ts-paywall-cta">
                                            💳 {{ __('ui.payplus_start_standing_order_btn') }}
                                        </a>
                                    @else
                                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="ts-paywall-cta ts-paywall-cta-dark">
                                            {{ __('ui.payplus_arrange_via_whatsapp') }}
                                        </a>
                                    @endif
                                </div>

                                {{-- Yearly CTA --}}
                                <div class="ts-pw-yearly" style="display:none;">
                                    @if ($payPlusConfigured)
                                        <form method="POST" action="{{ route('payplus.checkout') }}">
                                            @csrf
                                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                            <input type="hidden" name="cycle" value="yearly">
                                            <input type="hidden" name="extra_seats" value="0">
                                            <button type="submit" class="ts-paywall-cta">💳 {{ __('ui.payplus_start_standing_order_btn') }}</button>
                                        </form>
                                    @elseif ($upayYearly)
                                        <a href="{{ $upayYearly }}" target="_blank" rel="noopener" class="ts-paywall-cta">
                                            💳 {{ __('ui.payplus_start_standing_order_btn') }}
                                        </a>
                                    @else
                                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="ts-paywall-cta ts-paywall-cta-dark">
                                            {{ __('ui.payplus_arrange_via_whatsapp') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div style="text-align:center; padding: 18px 12px;">
                        <div style="font-size: 2.5rem; margin-bottom: 10px;">⏳</div>
                        <p style="font-size: 0.96rem; font-weight: 700; margin: 0;">
                            {{ __('ui.trial_paywall_staff_contact_owner') }}
                        </p>
                    </div>
                @endif
            </div>

            <div class="ts-paywall-foot">
                <a
                    href="https://api.whatsapp.com/send?phone=972502311554&text={{ rawurlencode('שלום, אני צריך עזרה בהפעלת המנוי עבור ' . $tenant->name) }}"
                    target="_blank"
                    rel="noopener"
                    style="color: #10b981; font-weight: 700; text-decoration: none;"
                >
                    💬 {{ __('ui.trial_paywall_need_help') }} (050-231-1554)
                </a>

                <div style="display:flex; align-items:center; gap:14px;">
                    <button
                        type="button"
                        onclick="window.location.reload()"
                        style="background:none; border:1px solid rgba(148,163,184,0.4); border-radius:8px; padding:6px 12px; font-size:0.8rem; font-weight:700; cursor:pointer; color:inherit;"
                    >
                        ↻ {{ __('ui.trial_paywall_already_paid_refresh') }}
                    </button>

                    <form method="POST" action="{{ filament()->getLogoutUrl() }}" style="margin:0;">
                        @csrf
                        <button
                            type="submit"
                            style="background:none; border:none; color:#ef4444; font-size:0.82rem; font-weight:700; cursor:pointer;"
                        >
                            {{ __('ui.trial_paywall_logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tsSetPaywallCycle(cycle) {
            var isYearly = cycle === 'yearly';
            document.getElementById('tsPwMonthlyBtn')?.classList.toggle('is-active', !isYearly);
            document.getElementById('tsPwYearlyBtn')?.classList.toggle('is-active', isYearly);
            document.querySelectorAll('.ts-pw-monthly').forEach(function (el) {
                el.style.display = isYearly ? 'none' : '';
            });
            document.querySelectorAll('.ts-pw-yearly').forEach(function (el) {
                el.style.display = isYearly ? '' : 'none';
            });
        }

        // Prevent closing via Escape key
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    </script>
@endif
