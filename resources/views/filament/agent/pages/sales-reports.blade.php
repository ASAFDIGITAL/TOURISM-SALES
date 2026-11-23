<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('ui.from_date') }}
                    </label>
                    <input 
                        type="date" 
                        wire:model.live="fromDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
                
                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('ui.to_date') }}
                    </label>
                    <input 
                        type="date" 
                        wire:model.live="toDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ __('ui.method') }}
                    </label>
                    <select
                        wire:model.live="paymentMethod"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">{{ __('ui.all_payment_methods') }}</option>
                        @foreach ($this->getPaymentMethodOptions() as $methodKey => $methodLabel)
                            <option value="{{ $methodKey }}">{{ $methodLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @php
            $stats = $this->getStats();
        @endphp

        <style>
            .ts-sales-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }
            .ts-sales-kpi-card {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }
            .ts-sales-kpi-icon {
                flex-shrink: 0;
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ts-sales-kpi-body {
                min-width: 0;
                flex: 1;
            }
            .ts-sales-kpi-label {
                font-size: 0.8rem;
                font-weight: 600;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin: 0;
            }
            .ts-sales-kpi-value {
                font-size: clamp(1.05rem, 1.45vw, 1.45rem);
                font-weight: 800;
                line-height: 1.25;
                margin: 3px 0 0;
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .ts-sales-kpi-sub {
                font-size: 0.74rem;
                color: #64748b;
                margin: 3px 0 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            @media (max-width: 1180px) {
                .ts-sales-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .ts-sales-kpi-value {
                    font-size: 1.35rem;
                }
            }
            @media (max-width: 640px) {
                .ts-sales-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="ts-sales-kpi-grid">
            <x-filament::section>
                <div class="ts-sales-kpi-card">
                    <div class="ts-sales-kpi-icon" style="background: rgba(16, 185, 129, 0.14); color: #10b981;">
                        <x-heroicon-o-currency-dollar class="w-6 h-6" />
                    </div>
                    <div class="ts-sales-kpi-body">
                        <h2 class="ts-sales-kpi-label">{{ __('ui.total_collected') }}</h2>
                        <p class="ts-sales-kpi-value" title="{{ $stats['revenue'] }}"><span dir="ltr">{{ $stats['revenue'] }}</span></p>
                        <p class="ts-sales-kpi-sub">{{ __('ui.payments') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="ts-sales-kpi-card">
                    <div class="ts-sales-kpi-icon" style="background: rgba(245, 158, 11, 0.14); color: #f59e0b;">
                        <x-heroicon-o-shopping-cart class="w-6 h-6" />
                    </div>
                    <div class="ts-sales-kpi-body">
                        <h2 class="ts-sales-kpi-label">{{ __('ui.total_sales_volume') }}</h2>
                        <p class="ts-sales-kpi-value" title="{{ $stats['sales'] }}"><span dir="ltr">{{ $stats['sales'] }}</span></p>
                        <p class="ts-sales-kpi-sub" title="{{ $stats['supplier_cost'] }}">{{ __('ui.supplier_cost') }}: <span dir="ltr">{{ $stats['supplier_cost'] }}</span></p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="ts-sales-kpi-card">
                    <div class="ts-sales-kpi-icon" style="background: rgba(16, 185, 129, 0.14); color: #059669;">
                        <x-heroicon-o-arrow-trending-up class="w-6 h-6" />
                    </div>
                    <div class="ts-sales-kpi-body">
                        <h2 class="ts-sales-kpi-label">{{ __('ui.net_profit') }}</h2>
                        <p class="ts-sales-kpi-value" title="{{ $stats['net_profit'] }}" style="color: #10b981;"><span dir="ltr">{{ $stats['net_profit'] }}</span></p>
                        <p class="ts-sales-kpi-sub">{{ __('ui.profit_margin_label', ['pct' => $stats['margin']]) }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="ts-sales-kpi-card">
                    <div class="ts-sales-kpi-icon" style="background: rgba(14, 165, 233, 0.14); color: #0ea5e9;">
                        <x-heroicon-o-briefcase class="w-6 h-6" />
                    </div>
                    <div class="ts-sales-kpi-body">
                        <h2 class="ts-sales-kpi-label">{{ __('ui.total_trips_count') ?? 'כמות טיולים' }}</h2>
                        <p class="ts-sales-kpi-value">{{ number_format((int) $stats['trips_count']) }}</p>
                        <p class="ts-sales-kpi-sub">{{ __('ui.trips') }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        @php
            $methodBreakdown = $this->getPaymentMethodBreakdown();
        @endphp

        <x-filament::section>
            <x-slot name="heading">{{ __('ui.breakdown_by_payment_method') }}</x-slot>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px;">
                @foreach ($methodBreakdown as $mb)
                    @php $isSelected = $paymentMethod === $mb['key']; @endphp
                    <button
                        type="button"
                        wire:click="setPaymentMethod('{{ $mb['key'] }}')"
                        style="text-align: start; padding: 12px 14px; border-radius: 12px; cursor: pointer; transition: all 0.15s ease; border: {{ $isSelected ? '2px solid #f59e0b' : '1px solid rgba(148, 163, 184, 0.28)' }}; background: {{ $isSelected ? 'rgba(245, 158, 11, 0.12)' : 'rgba(148, 163, 184, 0.06)' }};"
                    >
                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 700;">{{ $mb['label'] }}</div>
                        <div style="font-size: 1.15rem; font-weight: 800; margin-top: 3px;">{{ $mb['amount'] }}</div>
                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                            {{ __('ui.payments_count_short', ['count' => $mb['count']]) }}
                        </div>
                    </button>
                @endforeach
            </div>
        </x-filament::section>

        @php
            $employeeRows = $this->getEmployeePerformance();
        @endphp

        @if (count($employeeRows) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('ui.employee_performance_reports') }}
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; text-align: start;">
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.staff_member') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.trips') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.total_sales_volume') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.net_profit') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.total_collected') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.open_balances') }}</th>
                                <th style="padding: 10px 12px; text-align: start;">{{ __('ui.collection_rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employeeRows as $row)
                                <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.18);">
                                    <td style="padding: 10px 12px; font-weight: 700;">{{ $row['name'] }}</td>
                                    <td style="padding: 10px 12px;">{{ $row['trips_count'] }}</td>
                                    <td style="padding: 10px 12px; font-weight: 600;">{{ $row['sales'] }}</td>
                                    <td style="padding: 10px 12px; color: {{ $row['profit_raw'] >= 0 ? '#059669' : '#e11d48' }}; font-weight: 700;">{{ $row['profit'] }}</td>
                                    <td style="padding: 10px 12px; color: #10b981; font-weight: 600;">{{ $row['collected'] }}</td>
                                    <td style="padding: 10px 12px; color: {{ $row['balance_raw'] > 0 ? '#e11d48' : '#10b981' }}; font-weight: 600;">{{ $row['balance'] }}</td>
                                    <td style="padding: 10px 12px;">
                                        <span class="ts-support-badge {{ $row['rate'] >= 80 ? 'ts-badge-closed' : 'ts-badge-waiting_agent' }}">
                                            {{ $row['rate'] }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('ui.transactions_list') ?? 'פירוט עסקאות' }}</h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
