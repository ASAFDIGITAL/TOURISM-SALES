<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 20px;">
        {{-- Current Subscription Status Card --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('ui.current_subscription_status') }}</x-slot>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px;">
                <div style="padding: 14px; border-radius: 12px; background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(148, 163, 184, 0.25);">
                    <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">{{ __('ui.plan') }}</div>
                    <div style="font-size: 1.05rem; font-weight: 800; margin-top: 4px;">
                        {{ $tenant?->plan?->getLocalizedName() ?? __('ui.default_plan_name') }}
                    </div>
                </div>

                <div style="padding: 14px; border-radius: 12px; background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(148, 163, 184, 0.25);">
                    <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">{{ __('ui.team_seats') }}</div>
                    <div style="font-size: 1.05rem; font-weight: 800; margin-top: 4px;">
                        {{ $tenant?->getActiveUsersCount() ?? 1 }} / {{ $tenant?->getMaxUsers() ?? 1 }}
                    </div>
                </div>

                <div style="padding: 14px; border-radius: 12px; background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(148, 163, 184, 0.25);">
                    <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">{{ __('ui.payplus_standing_order') }}</div>
                    <div style="font-size: 0.95rem; font-weight: 800; margin-top: 4px; color: {{ $tenant?->hasActivePayPlusStandingOrder() ? '#10b981' : '#d97706' }};">
                        @if ($tenant?->hasActivePayPlusStandingOrder())
                            ✓ {{ __('ui.payplus_active_standing_order') }}
                            @if ($tenant->payplus_card_last4)
                                (•••• {{ $tenant->payplus_card_last4 }})
                            @endif
                        @elseif ($tenant?->isLifetime())
                            ∞ {{ __('ui.subscription_lifetime_short') }}
                        @elseif ($tenant?->isOnTrial())
                            ★ {{ __('ui.trial_days_left_short', ['days' => $tenant->getTrialDaysLeft()]) }}
                        @else
                            {{ __('ui.payplus_not_active_yet') }}
                        @endif
                    </div>
                </div>

                <div style="padding: 14px; border-radius: 12px; background: rgba(15, 23, 42, 0.04); border: 1px solid rgba(148, 163, 184, 0.25);">
                    <div style="font-size: 0.76rem; color: #64748b; font-weight: 600;">{{ __('ui.expires_at') }}</div>
                    <div style="font-size: 1.05rem; font-weight: 800; margin-top: 4px;">
                        {{ $tenant?->isLifetime() ? '∞' : ($tenant?->expires_at ? \Carbon\Carbon::parse($tenant->expires_at)->format('d/m/Y') : '-') }}
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Choose Plan & Activate Subscription --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('ui.payplus_activate_standing_order_heading') }}</x-slot>
            <x-slot name="description">
                {{ __('ui.payplus_activate_standing_order_desc', ['vat' => $vatRate]) }}
            </x-slot>

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 18px;">
                <div style="display: inline-flex; gap: 6px; padding: 4px; border-radius: 11px; background: rgba(148, 163, 184, 0.2);">
                    <button
                        type="button"
                        wire:click="setCycle('monthly')"
                        style="border: none; padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; {{ $cycle === 'monthly' ? 'background:#0f172a; color:#ffffff;' : 'background:transparent; color:inherit;' }}"
                    >
                        {{ __('ui.billing_monthly') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setCycle('yearly')"
                        style="border: none; padding: 7px 14px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; {{ $cycle === 'yearly' ? 'background:#0f172a; color:#fbbf24;' : 'background:transparent; color:inherit;' }}"
                    >
                        {{ __('ui.billing_yearly') }} ({{ __('ui.save_12_percent') }})
                    </button>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 0.82rem; font-weight: 700;">{{ __('ui.extra_seats_label') }}:</label>
                    <input
                        type="number"
                        min="0"
                        max="50"
                        wire:model.live="extraSeats"
                        class="ts-support-input"
                        style="width: 82px; padding: 6px 10px; text-align: center;"
                    >
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                @foreach ($planSummaries as $item)
                    @php
                        $plan = $item['plan'];
                        $totals = $item['totals'];
                        $sym = $plan->getCurrencySymbol();
                        $isCurrent = $tenant?->plan_id === $plan->id;
                    @endphp
                    <div style="border-radius: 16px; padding: 20px; border: {{ $plan->is_featured ? '2px solid #f59e0b' : '1px solid rgba(148, 163, 184, 0.3)' }}; display: flex; flex-direction: column; justify-content: space-between; gap: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                <strong style="font-size: 1.05rem;">{{ $plan->getLocalizedName() }}</strong>
                                @if ($isCurrent)
                                    <span class="ts-support-badge ts-badge-closed">{{ __('ui.current_plan_badge') }}</span>
                                @elseif ($plan->is_featured)
                                    <span class="ts-support-badge ts-badge-waiting_agent">{{ __('ui.most_popular') }}</span>
                                @endif
                            </div>

                            <div style="margin-top: 10px;">
                                <span style="font-size: 1.7rem; font-weight: 800;">{{ $sym }}{{ number_format($totals['subtotal'], 0) }}</span>
                                <span style="font-size: 0.8rem; color: #64748b;">
                                    / {{ $cycle === 'yearly' ? __('ui.year_short') : __('ui.month_short') }} {{ __('ui.plus_vat') }}
                                </span>
                            </div>
                            <div style="font-size: 0.78rem; color: #10b981; font-weight: 700; margin-top: 2px;">
                                {{ __('ui.total_including_vat', ['total' => $sym . number_format($totals['total'], 2), 'vat' => $totals['vat_rate']]) }}
                            </div>

                            <div style="margin-top: 10px; font-size: 0.82rem; color: #64748b;">
                                • {{ __('ui.plan_feat_users', ['count' => $plan->included_users + $extraSeats]) }}
                            </div>
                        </div>

                        @if ($payPlusConfigured)
                            <form method="POST" action="{{ route('payplus.checkout') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <input type="hidden" name="cycle" value="{{ $cycle }}">
                                <input type="hidden" name="extra_seats" value="{{ $extraSeats }}">
                                <x-filament::button type="submit" color="primary" icon="heroicon-o-credit-card" style="width: 100%;">
                                    {{ __('ui.payplus_start_standing_order_btn') }}
                                </x-filament::button>
                            </form>
                        @else
                            <a
                                href="https://api.whatsapp.com/send?phone=972502311554&text={{ rawurlencode('שלום, אני מעוניין להפעיל הוראת קבע עבור ' . ($tenant?->name ?? '') . ' בחבילת ' . $plan->getLocalizedName('he')) }}"
                                target="_blank"
                                rel="noopener"
                                style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 14px; border-radius: 10px; background: #0f172a; color: #fbbf24; font-size: 0.84rem; font-weight: 700; text-decoration: none;"
                            >
                                {{ __('ui.payplus_arrange_via_whatsapp') }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Digital Subscription Invoices (PayPlus Invoice+) --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('ui.subscription_invoices') }}</x-slot>
            <x-slot name="description">{{ __('ui.subscription_invoices_desc') }}</x-slot>

            @if ($invoices->isEmpty())
                <div style="padding: 24px; text-align: center; color: #64748b; font-size: 0.88rem;">
                    {{ __('ui.no_subscription_invoices_yet') }}
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.86rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; text-align: start;">
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.date') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.description') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.subtotal_before_vat') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.vat_label') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.total_amount') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.status') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.invoice_document') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $inv)
                                @php $sym = $inv->getCurrencySymbol(); @endphp
                                <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.18);">
                                    <td style="padding: 10px 12px;">{{ ($inv->paid_at ?? $inv->created_at)->format('d/m/Y') }}</td>
                                    <td style="padding: 10px 12px; font-weight: 600;">{{ $inv->description ?: ($inv->plan?->getLocalizedName() ?? '-') }}</td>
                                    <td style="padding: 10px 12px;">{{ $sym }}{{ number_format((float) $inv->subtotal_amount, 2) }}</td>
                                    <td style="padding: 10px 12px;">{{ $sym }}{{ number_format((float) $inv->vat_amount, 2) }}</td>
                                    <td style="padding: 10px 12px; font-weight: 800;">{{ $sym }}{{ number_format((float) $inv->total_amount, 2) }}</td>
                                    <td style="padding: 10px 12px;">
                                        <span class="ts-support-badge {{ $inv->status === 'paid' ? 'ts-badge-closed' : ($inv->status === 'failed' ? 'ts-badge-open' : 'ts-badge-waiting_agent') }}">
                                            {{ __('ui.invoice_status_' . $inv->status) }}
                                        </span>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        @if ($inv->payplus_invoice_url)
                                            <a href="{{ $inv->payplus_invoice_url }}" target="_blank" rel="noopener" style="color: #d97706; font-weight: 700; text-decoration: underline;">
                                                {{ __('ui.download_invoice_pdf') }} {{ $inv->payplus_invoice_number ? '#' . $inv->payplus_invoice_number : '' }}
                                            </a>
                                        @else
                                            <span style="color: #94a3b8;">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
