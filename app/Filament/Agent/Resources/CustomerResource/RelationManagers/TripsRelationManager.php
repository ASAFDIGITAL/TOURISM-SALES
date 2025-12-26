<?php

namespace App\Filament\Agent\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripsRelationManager extends RelationManager
{
    protected static string $relationship = 'trips';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ui.trip'))
                    ->url(fn ($record): string => \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $record->id]))
                    ->color('primary')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination')
                    ->label(__('ui.destination'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('ui.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->tenant->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('ui.paid'))
                    ->getStateUsing(fn ($record) => $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->tenant->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    }),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('ui.balance'))
                    ->getStateUsing(fn ($record) => $record->total_amount - $record->payments->sum('amount'))
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->tenant->currency ?? 'USD';
                        $symbol = match($currency) { 'ILS' => '₪', 'EUR' => '€', 'GBP' => '£', default => '$' };
                        return $symbol . ' ' . number_format($state, 2);
                    })
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
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
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('ui.trip') . ' (' . __('ui.new') . ')'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
