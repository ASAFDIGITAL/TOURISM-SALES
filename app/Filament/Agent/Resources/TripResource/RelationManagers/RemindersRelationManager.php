<?php

namespace App\Filament\Agent\Resources\TripResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('ui.reminders');
    }

    public static function getModelLabel(): string
    {
        return __('ui.reminder');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('channel')
                    ->label(__('ui.channel'))
                    ->options([
                        'email' => __('ui.email'),
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->label(__('ui.scheduled_at'))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'pending' => __('ui.pending'),
                        'sent' => __('ui.sent'),
                        'failed' => __('ui.failed'),
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('message')
                    ->label(__('ui.message'))
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('ui.channel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => __('ui.email'),
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('ui.scheduled_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sent' => 'success',
                        'failed' => 'danger',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
