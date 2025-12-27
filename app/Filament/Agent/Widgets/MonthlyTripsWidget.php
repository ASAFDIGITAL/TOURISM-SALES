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
        $filterLabel = __('ui.filter');
        $resetLabel = __('ui.reset');
        
        $heading = $this->fromDate || $this->toDate 
            ? __('ui.trips_of_month') 
            : __('ui.all_trips');
        
        return new HtmlString("
            <div class='space-y-4 w-full'>
                <div class='text-lg font-semibold'>{$heading}</div>
                <div class='flex gap-4 items-end'>
                    <div class='flex-1'>
                        <label class='block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1'>" . __('ui.from_date') . "</label>
                        <input 
                            type='date' 
                            wire:model.defer='fromDate'
                            class='w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500'
                        />
                    </div>
                    <div class='flex-1'>
                        <label class='block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1'>" . __('ui.to_date') . "</label>
                        <input 
                            type='date' 
                            wire:model.defer='toDate'
                            class='w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500'
                        />
                    </div>
                    <div class='flex gap-2'>
                        <button 
                            wire:click='\$refresh'
                            type='button'
                            class='inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'>
                            {$filterLabel}
                        </button>
                        <button 
                            wire:click='resetDates'
                            type='button'
                            class='inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2'>
                            {$resetLabel}
                        </button>
                    </div>
                </div>
            </div>
        ");
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Trip::where('tenant_id', Auth::user()->tenant_id)
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
                        $currency = Auth::user()->tenant->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('ui.paid'))
                    ->getStateUsing(fn (Trip $record) => $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state) {
                        $currency = Auth::user()->tenant->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('ui.balance'))
                    ->getStateUsing(fn (Trip $record) => $record->total_amount - $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state) {
                        $currency = Auth::user()->tenant->currency ?? 'USD';
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
            ->defaultSort('start_date', 'desc');
    }
}
