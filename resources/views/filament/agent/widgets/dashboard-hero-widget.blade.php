<x-filament-widgets::widget>
    <div class="ts-dash-hero">
        <div class="ts-dash-hero-glow"></div>

        <div class="ts-dash-hero-top">
            <div>
                <div class="ts-dash-hero-eyebrow">
                    <span class="ts-dash-hero-dot"></span>
                    <span>{{ $agencyName }}</span>
                    <span style="opacity: 0.45;">•</span>
                    <span>{{ $todayLabel }}</span>
                </div>
                <h2 class="ts-dash-hero-title">
                    {{ $greeting }}, {{ $userName }}
                </h2>
                <p class="ts-dash-hero-sub">
                    {{ __('ui.dashboard_hero_subtitle') }}
                </p>
            </div>

            <div class="ts-dash-quick-actions">
                <a href="{{ $urls['new_trip'] }}" class="ts-dash-qa-btn ts-dash-qa-primary">
                    <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ __('ui.quick_new_trip') }}</span>
                </a>

                <a href="{{ $urls['new_customer'] }}" class="ts-dash-qa-btn ts-dash-qa-ghost">
                    <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                    <span>{{ __('ui.quick_new_customer') }}</span>
                </a>

                <a href="{{ $urls['sales_reports'] }}" class="ts-dash-qa-btn ts-dash-qa-ghost">
                    <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                    <span>{{ __('ui.sales_reports') }}</span>
                </a>

                @if ($remindersEnabled && $urls['reminder_rules'])
                    <a href="{{ $urls['reminder_rules'] }}" class="ts-dash-qa-btn ts-dash-qa-ghost">
                        <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                        </svg>
                        <span>{{ __('ui.smart_reminder_rules') }}</span>
                    </a>
                @endif

                <a href="{{ $urls['mobile_alerts'] }}" class="ts-dash-qa-btn ts-dash-qa-ghost">
                    <svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                    <span>{{ __('ui.mobile_app_notifications') }}</span>
                </a>
            </div>
        </div>

        <div class="ts-dash-hero-pulse">
            <div class="ts-dash-pulse-item">
                <span class="ts-dash-pulse-label">{{ __('ui.customers_in_crm') }}</span>
                <span class="ts-dash-pulse-value">{{ number_format($customersCount) }}</span>
            </div>

            <div class="ts-dash-pulse-item">
                <span class="ts-dash-pulse-label">{{ __('ui.upcoming_departures_14d') }}</span>
                <span class="ts-dash-pulse-value" style="color: #fbbf24;">{{ number_format($upcomingDeparturesCount) }}</span>
            </div>

            <div class="ts-dash-pulse-item ts-dash-pulse-progress">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;">
                    <span class="ts-dash-pulse-label">{{ __('ui.collection_rate') }}</span>
                    <span class="ts-dash-pulse-value" style="font-size: 0.92rem; color: #34d399;">
                        {{ $collectionRate }}% · {{ $totalCollectedFormatted }}
                    </span>
                </div>
                <div class="ts-dash-bar-track">
                    <div class="ts-dash-bar-fill" style="width: {{ $collectionRate }}%;"></div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
