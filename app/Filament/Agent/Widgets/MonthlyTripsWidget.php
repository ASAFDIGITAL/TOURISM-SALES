<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Trip;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\HtmlString;

class MonthlyTripsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public function mount(): void
    {
        // Default to show all trips (no date filter)
        $this->fromDate = null;
        $this->toDate = null;
    }

    public function resetDates(): void
    {
        // Reset to show all trips
        $this->fromDate = null;
        $this->toDate = null;
    }

    public function getTableHeading(): string | HtmlString
    {
        $filterLabel = e(__('ui.filter'));
        $resetLabel = e(__('ui.reset'));
        $fromLabel = e(__('ui.from_date'));
        $toLabel = e(__('ui.to_date'));

        $heading = e($this->fromDate || $this->toDate
            ? __('ui.trips_of_month')
            : __('ui.all_trips'));

        return new HtmlString("
            <div class='ts-filter-header'>
                <div class='ts-filter-title'>{$heading}</div>
                <div class='ts-filter-bar'>
                    <div class='ts-filter-field'>
                        <label class='ts-filter-label'>{$fromLabel}</label>
                        <input type='date' wire:model.defer='fromDate' class='ts-filter-input' />
                    </div>
                    <div class='ts-filter-field'>
                        <label class='ts-filter-label'>{$toLabel}</label>
                        <input type='date' wire:model.defer='toDate' class='ts-filter-input' />
                    </div>
                    <div class='ts-filter-actions'>
                        <button wire:click='\$refresh' type='button' class='ts-filter-btn ts-filter-btn-primary'>{$filterLabel}</button>
                        <button wire:click='resetDates' type='button' class='ts-filter-btn ts-filter-btn-secondary'>{$resetLabel}</button>
                    </div>
                </div>
            </div>
        ");
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Trip::where('tenant_id', Auth::user()->getActiveTenantId())
                    ->when($this->fromDate, fn ($query) => $query->whereDate('start_date', '>=', $this->fromDate))
                    ->when($this->toDate, fn ($query) => $query->whereDate('start_date', '<=', $this->toDate))
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('ui.customer'))
                    ->url(fn (Trip $record): string => \App\Filament\Agent\Resources\CustomerResource::getUrl('edit', ['record' => $record->customer_id]))
                    ->color('primary')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('destination')
                    ->label(__('ui.destination')),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('ui.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
                    ->getStateUsing(fn ($record) => $record->total_amount)
                    ->formatStateUsing(function ($state) {
                        $currency = Auth::user()->getActiveTenant()?->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('ui.paid'))
                    ->getStateUsing(fn (Trip $record) => $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state) {
                        $currency = Auth::user()->getActiveTenant()?->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('ui.balance'))
                    ->getStateUsing(fn (Trip $record) => $record->total_amount - $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state) {
                        $currency = Auth::user()->getActiveTenant()?->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    })
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
            ])
            ->actions([
                \App\Filament\Agent\Actions\SendTripMessageAction::makeTableAction(),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
