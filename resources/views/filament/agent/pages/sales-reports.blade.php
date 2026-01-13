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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-success-100 rounded-full dark:bg-success-900/20">
                        <x-heroicon-o-currency-dollar class="w-8 h-8 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <h2 class="text-lg font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_collected') }}</h2>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['revenue'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
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
                        <h2 class="text-lg font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_sales_volume') }}</h2>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['sales'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.trips') }}
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
                        <h2 class="text-lg font-medium text-gray-500 dark:text-gray-400">{{ __('ui.total_trips_count') ?? 'כמות טיולים' }}</h2>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['trips_count'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ __('ui.trips') }}
                        </p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ __('ui.transactions_list') ?? 'פירוט עסקאות' }}</h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
