<x-filament-widgets::widget>
    <div class="ts-dash-panel">
        <div class="ts-dash-panel-header">
            <div class="ts-dash-tabs">
                <button
                    type="button"
                    wire:click="setTab('departures')"
                    class="ts-dash-tab {{ $activeTab === 'departures' ? 'is-active' : '' }}"
                >
                    <span>{{ __('ui.upcoming_departures') }}</span>
                    <span class="ts-dash-tab-count">{{ $departures->count() }}</span>
                </button>

                @if ($remindersEnabled)
                    <button
                        type="button"
                        wire:click="setTab('reminders')"
                        class="ts-dash-tab {{ $activeTab === 'reminders' ? 'is-active' : '' }}"
                    >
                        <span>{{ __('ui.upcoming_reminders') }}</span>
                        <span class="ts-dash-tab-count">{{ $reminders->count() }}</span>
                    </button>
                @endif
            </div>

            <a
                href="{{ $activeTab === 'reminders' && $allRemindersUrl ? $allRemindersUrl : $allTripsUrl }}"
                class="ts-dash-panel-link"
            >
                {{ __('ui.view_all') }}
            </a>
        </div>

        <div class="ts-dash-panel-body">
            @if ($activeTab === 'departures')
                @forelse ($departures as $item)
                    <a href="{{ $item['url'] }}" class="ts-dash-row">
                        <div class="ts-dash-row-main">
                            <div class="ts-dash-countdown {{ $item['days_until'] <= 2 ? 'is-urgent' : ($item['days_until'] <= 7 ? 'is-soon' : 'is-normal') }}">
                                @if ($item['days_until'] === 0)
                                    {{ __('ui.today') }}
                                @elseif ($item['days_until'] === 1)
                                    {{ __('ui.tomorrow') }}
                                @else
                                    {{ __('ui.in_x_days', ['days' => $item['days_until']]) }}
                                @endif
                            </div>

                            <div style="min-width: 0; flex: 1;">
                                <div class="ts-dash-row-title">{{ $item['destination'] }}</div>
                                <div class="ts-dash-row-sub">
                                    {{ $item['customer'] }} · {{ $item['date'] }}
                                </div>
                            </div>
                        </div>

                        @if ($canViewFinancials)
                            <div style="flex-shrink: 0;">
                                @if ($item['balance'] > 0)
                                    <span class="ts-support-badge ts-badge-open">
                                        {{ __('ui.balance_due_short', ['amount' => $item['balance_formatted']]) }}
                                    </span>
                                @else
                                    <span class="ts-support-badge ts-badge-closed">
                                        {{ __('ui.paid_in_full') }}
                                    </span>
                                @endif
                            </div>
                        @endif
                    </a>
                @empty
                    <div class="ts-dash-empty">
                        {{ __('ui.no_upcoming_departures') }}
                    </div>
                @endforelse
            @else
                @forelse ($reminders as $reminder)
                    <div class="ts-dash-row">
                        <div class="ts-dash-row-main">
                            <div class="ts-dash-countdown is-soon">
                                {{ optional($reminder->scheduled_at)->format('d/m H:i') }}
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <div class="ts-dash-row-title">
                                    {{ $reminder->trip?->customer?->name ?? __('ui.general_reminder') }}
                                </div>
                                <div class="ts-dash-row-sub">
                                    {{ \Illuminate\Support\Str::limit($reminder->message, 60) }}
                                </div>
                            </div>
                        </div>
                        <span class="ts-support-badge ts-badge-in_progress">
                            {{ __('ui.channel_' . ($reminder->channel ?? 'push')) }}
                        </span>
                    </div>
                @empty
                    <div class="ts-dash-empty">
                        {{ __('ui.no_upcoming_reminders') }}
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
