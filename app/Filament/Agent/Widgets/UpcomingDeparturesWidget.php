<?php

namespace App\Filament\Agent\Widgets;

use App\Filament\Agent\Resources\ReminderResource;
use App\Filament\Agent\Resources\TripResource;
use App\Models\Reminder;
use App\Models\Trip;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class UpcomingDeparturesWidget extends Widget
{
    protected static string $view = 'filament.agent.widgets.upcoming-departures-widget';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public string $activeTab = 'departures';

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['departures', 'reminders'], true)) {
            $this->activeTab = $tab;
        }
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $tenant = $user?->getActiveTenant();
        $tenantId = $user?->getActiveTenantId();

        $currency = $tenant?->currency ?? 'ILS';
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $departures = $tenantId
            ? Trip::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('start_date', '>=', now()->toDateString())
                ->whereDate('start_date', '<=', now()->addDays(21)->toDateString())
                ->with(['customer', 'payments'])
                ->orderBy('start_date')
                ->limit(6)
                ->get()
                ->map(function (Trip $trip) use ($symbol) {
                    $paid = (float) $trip->payments->sum('amount');
                    $balance = max(0, (float) $trip->total_amount - $paid);
                    $daysUntil = (int) now()->startOfDay()->diffInDays($trip->start_date->copy()->startOfDay(), false);

                    return [
                        'id' => $trip->id,
                        'customer' => $trip->customer?->name ?? '-',
                        'destination' => $trip->destination ?: $trip->title,
                        'date' => $trip->start_date->format('d/m/Y'),
                        'days_until' => $daysUntil,
                        'balance' => $balance,
                        'balance_formatted' => $symbol . number_format($balance, 0),
                        'url' => TripResource::getUrl('edit', ['record' => $trip->id]),
                    ];
                })
            : collect();

        $remindersEnabled = $tenant?->hasRemindersEnabled() ?? true;

        $reminders = ($tenantId && $remindersEnabled)
            ? Reminder::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->with(['trip.customer'])
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get()
            : collect();

        return [
            'departures' => $departures,
            'reminders' => $reminders,
            'remindersEnabled' => $remindersEnabled,
            'allTripsUrl' => TripResource::getUrl('index'),
            'allRemindersUrl' => $remindersEnabled ? ReminderResource::getUrl('index') : null,
        ];
    }
}
