<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use App\Services\WebPushService;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class CheckUpcomingTrips extends Command
{
    protected $signature = 'app:check-upcoming-trips';

    protected $description = 'Check for trips and flights departing today, tomorrow, or in 3 days and notify agents';

    public function handle(WebPushService $webPushService): int
    {
        $windows = [
            ['days' => 3, 'date' => Carbon::now()->addDays(3)->format('Y-m-d'), 'warning' => false],
            ['days' => 1, 'date' => Carbon::now()->addDay()->format('Y-m-d'), 'warning' => true],
            ['days' => 0, 'date' => Carbon::now()->format('Y-m-d'), 'warning' => true],
        ];

        foreach ($windows as $window) {
            $trips = Trip::withoutGlobalScopes()
                ->with(['customer', 'tenant'])
                ->whereDate('start_date', $window['date'])
                ->whereIn('status', ['confirmed', 'active'])
                ->get();

            $this->info("Found {$trips->count()} trips starting on {$window['date']} (in {$window['days']} days)");

            foreach ($trips as $trip) {
                $agents = User::where('tenant_id', $trip->tenant_id)
                    ->where('notify_flights', true)
                    ->get();

                $customerName = $trip->customer?->name ?? 'Customer';
                $tripUrl = \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent');
                $firstFlight = is_array($trip->flights) && count($trip->flights) > 0 ? $trip->flights[0] : null;
                $flightSuffix = '';
                if ($firstFlight) {
                    $flightParts = array_filter([
                        $firstFlight['airline'] ?? null,
                        $firstFlight['flight_number'] ?? null,
                        !empty($firstFlight['departure_time']) ? Carbon::parse($firstFlight['departure_time'])->format('H:i') : null,
                    ]);
                    if (count($flightParts) > 0) {
                        $flightSuffix = ' (' . implode(' ', $flightParts) . ')';
                    }
                }

                foreach ($agents as $agent) {
                    $originalLocale = App::getLocale();
                    $locale = $agent->language ?: ($agent->tenant?->language ?: 'he');
                    App::setLocale($locale);

                    $title = __('ui.upcoming_trip', ['destination' => $trip->destination ?: $trip->title]);
                    $body = match ($window['days']) {
                        0 => __('ui.trip_starts_today', ['customer' => $customerName]) . $flightSuffix,
                        1 => __('ui.trip_starts_tomorrow', ['customer' => $customerName]) . $flightSuffix,
                        default => __('ui.trip_starts_in_days', [
                            'customer' => $customerName,
                            'days' => $window['days'],
                            'date' => $trip->start_date->format('d/m/Y'),
                        ]) . $flightSuffix,
                    };

                    $notification = Notification::make()
                        ->title($title)
                        ->icon('heroicon-o-calendar')
                        ->body($body)
                        ->actions([
                            Action::make('view')
                                ->label(__('ui.view_trip'))
                                ->url($tripUrl, shouldOpenInNewTab: true),
                        ]);

                    if ($window['warning']) {
                        $notification->warning();
                    }

                    $notification->sendToDatabase($agent);

                    $webPushService->sendToUser(
                        user: $agent,
                        title: $title,
                        body: $body,
                        url: $tripUrl,
                        tag: "flight-trip-{$trip->id}-d{$window['days']}",
                        locale: $locale,
                    );

                    App::setLocale($originalLocale);
                }
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
