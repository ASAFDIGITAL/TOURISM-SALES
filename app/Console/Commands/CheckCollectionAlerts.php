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

class CheckCollectionAlerts extends Command
{
    protected $signature = 'app:check-collection-alerts';

    protected $description = 'Notify agents (in-app & phone push) about trips with open payment balances nearing departure';

    public function handle(WebPushService $webPushService): int
    {
        $cutoffDate = Carbon::now()->addDays(7)->format('Y-m-d');

        $trips = Trip::withoutGlobalScopes()
            ->with(['customer', 'tenant', 'payments'])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $cutoffDate)
            ->get()
            ->filter(fn (Trip $trip): bool => (float) $trip->balance > 0);

        $this->info("Found {$trips->count()} trips with open balances needing collection attention.");

        foreach ($trips as $trip) {
            $agents = User::where('tenant_id', $trip->tenant_id)
                ->where('notify_collections', true)
                ->get();

            $currency = $trip->tenant?->currency ?? 'ILS';
            $symbol = match ($currency) {
                'ILS' => '₪',
                'EUR' => '€',
                'GBP' => '£',
                default => '$',
            };
            $formattedBalance = $symbol . number_format((float) $trip->balance, 2);
            $customerName = $trip->customer?->name ?? 'Customer';
            $tripUrl = \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent');

            foreach ($agents as $agent) {
                $originalLocale = App::getLocale();
                $locale = $agent->language ?: ($agent->tenant?->language ?: 'he');
                App::setLocale($locale);

                $title = __('ui.collection_alert_title', ['customer' => $customerName]);
                $body = __('ui.collection_alert_body', [
                    'customer' => $customerName,
                    'trip' => $trip->title,
                    'balance' => $formattedBalance,
                    'date' => $trip->start_date?->format('d/m/Y') ?? '',
                ]);

                Notification::make()
                    ->title($title)
                    ->icon('heroicon-o-banknotes')
                    ->warning()
                    ->body($body)
                    ->actions([
                        Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url($tripUrl, shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);

                $webPushService->sendToUser(
                    user: $agent,
                    title: $title,
                    body: $body,
                    url: $tripUrl,
                    tag: "collection-trip-{$trip->id}",
                    locale: $locale,
                );

                App::setLocale($originalLocale);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
