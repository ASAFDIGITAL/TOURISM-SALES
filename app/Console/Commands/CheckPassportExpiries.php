<?php

namespace App\Console\Commands;

use App\Filament\Agent\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Services\WebPushService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class CheckPassportExpiries extends Command
{
    protected $signature = 'app:check-passport-expiries';

    protected $description = 'Alert agencies when a customer on an upcoming trip has a passport expiring within 6 months of departure';

    public function handle(WebPushService $webPush): int
    {
        $trips = Trip::withoutGlobalScopes()
            ->with(['customer', 'tenant'])
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->whereNotNull('start_date')
            ->whereDate('start_date', '>=', now()->toDateString())
            ->whereDate('start_date', '<=', now()->addDays(60)->toDateString())
            ->get();

        $alertedCount = 0;

        foreach ($trips as $trip) {
            $tenant = $trip->tenant;
            $customer = $trip->customer;

            if (! $customer || ! $customer->passport_expires_at) {
                continue;
            }

            if ($tenant && $tenant->status !== 'active') {
                continue;
            }

            if (! $customer->hasPassportExpiryRisk($trip->start_date, 6)) {
                continue;
            }

            $dedupeKey = "passport-alert-trip-{$trip->id}-" . now()->format('o-W');
            if (Cache::has($dedupeKey)) {
                continue;
            }

            $recipients = User::where('tenant_id', $trip->tenant_id)
                ->where('role', 'agent')
                ->when(
                    $trip->created_by_user_id,
                    fn ($q, $creatorId) => $q->where(fn ($inner) => $inner
                        ->where('id', $creatorId)
                        ->orWhere('agency_role', User::AGENCY_ROLE_OWNER))
                )
                ->get();

            $tripUrl = TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent');

            foreach ($recipients as $agent) {
                $origLocale = App::getLocale();
                $agentLocale = $agent->language ?: ($tenant?->language ?: 'he');
                App::setLocale($agentLocale);

                $title = __('ui.passport_alert_title', ['customer' => $customer->name]);
                $body = __('ui.passport_alert_body', [
                    'customer' => $customer->name,
                    'expiry' => $customer->passport_expires_at->format('d/m/Y'),
                    'trip' => $trip->title,
                ]);

                Notification::make()
                    ->title($title)
                    ->body($body)
                    ->icon('heroicon-o-exclamation-triangle')
                    ->danger()
                    ->actions([
                        Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url($tripUrl),
                    ])
                    ->sendToDatabase($agent);

                if (! $tenant || $tenant->canUseChannel('push')) {
                    try {
                        $webPush->sendToUser($agent, $title, $body, url: $tripUrl, tag: "passport-{$trip->id}", locale: $agentLocale);
                    } catch (\Throwable) {
                    }
                }

                App::setLocale($origLocale);
            }

            Cache::put($dedupeKey, true, now()->addDays(7));
            $alertedCount++;
        }

        $this->info("Sent {$alertedCount} passport expiry alert(s).");

        return self::SUCCESS;
    }
}
