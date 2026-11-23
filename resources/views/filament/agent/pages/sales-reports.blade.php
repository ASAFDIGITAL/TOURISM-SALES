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
            </div>
        </x-filament::section>

        @php
            $stats = $this->getStats();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-success-100 rounded-full dark:bg-success-900/20">
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_collected') }}</h2>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['revenue'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.payments') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-primary-100 rounded-full dark:bg-primary-900/20">
                        <x-heroicon-o-shopping-cart class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_sales_volume') }}</h2>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['sales'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.supplier_cost') }}: {{ $stats['supplier_cost'] }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-success-100 rounded-full dark:bg-success-900/20">
                        <x-heroicon-o-arrow-trending-up class="w-8 h-8 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('ui.net_profit') }}</h2>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['net_profit'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.profit_margin_label', ['pct' => $stats['margin']]) }}
                        </p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-info-100 rounded-full dark:bg-info-900/20">
                        <x-heroicon-o-briefcase class="w-8 h-8 text-info-600 dark:text-info-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_trips_count') ?? 'כמות טיולים' }}</h2>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['trips_count'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.trips') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>
        </div>

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
