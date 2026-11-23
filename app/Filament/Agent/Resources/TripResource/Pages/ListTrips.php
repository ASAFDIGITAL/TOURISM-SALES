<?php

namespace App\Filament\Agent\Resources\TripResource\Pages;

use App\Filament\Agent\Resources\TripResource;
use App\Models\Trip;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $base = fn (): Builder => TripResource::getEloquentQuery();

        $quotesScope = fn (Builder $q): Builder => $q->where('status', Trip::STATUS_QUOTE);

        $upcomingScope = fn (Builder $q): Builder => $q
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->whereNotNull('start_date')
            ->whereDate('start_date', '>=', now()->toDateString())
            ->whereDate('start_date', '<=', now()->addDays(30)->toDateString());

        $unpaidScope = fn (Builder $q): Builder => $q
            ->where('status', '!=', Trip::STATUS_CANCELLED)
            ->where('total_amount', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(amount) FROM payments WHERE payments.trip_id = trips.id), 0) < trips.total_amount');

        $confirmedScope = fn (Builder $q): Builder => $q->where('status', Trip::STATUS_CONFIRMED);
        $completedScope = fn (Builder $q): Builder => $q->where('status', Trip::STATUS_COMPLETED);

        return [
            'all' => Tab::make(__('ui.tab_all_tenants'))
                ->badge($base()->count()),

            'quotes' => Tab::make(__('ui.tab_trip_quotes'))
                ->badge($quotesScope($base())->count())
                ->badgeColor('primary')
                ->modifyQueryUsing($quotesScope),

            'upcoming' => Tab::make(__('ui.tab_trip_upcoming'))
                ->badge($upcomingScope($base())->count())
                ->badgeColor('info')
                ->modifyQueryUsing($upcomingScope),

            'unpaid' => Tab::make(__('ui.tab_trip_unpaid'))
                ->badge($unpaidScope($base())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing($unpaidScope),

            'confirmed' => Tab::make(__('ui.confirmed'))
                ->badge($confirmedScope($base())->count())
                ->badgeColor('success')
                ->modifyQueryUsing($confirmedScope),

            'completed' => Tab::make(__('ui.completed'))
                ->badge($completedScope($base())->count())
                ->badgeColor('gray')
                ->modifyQueryUsing($completedScope),
        ];
    }
}
