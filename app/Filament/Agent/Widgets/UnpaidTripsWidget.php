<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Trip;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UnpaidTripsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('ui.unpaid_trips');
    }

    public function table(Table $table): Table
    {
        $currency = Auth::user()->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        return $table
            ->query(
                Trip::where('tenant_id', Auth::user()->tenant_id)
                    ->where('status', '!=', 'cancelled')
                    ->whereRaw('total_amount > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.trip_id = trips.id)')
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('ui.customer'))
                    ->url(fn (Trip $record): string => \App\Filament\Agent\Resources\CustomerResource::getUrl('edit', ['record' => $record->customer_id]))
                    ->color('primary')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ui.trip')),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
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
                    ->color('danger')
                    ->weight('bold'),
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
            ]);
    }
}
