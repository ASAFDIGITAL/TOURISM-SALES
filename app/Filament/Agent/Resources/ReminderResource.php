<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\ReminderResource\Pages;
use App\Models\Reminder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReminderResource extends Resource
{
    protected static ?string $model = Reminder::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    public static function getModelLabel(): string
    {
        return __('ui.reminder');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.reminders');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.reminders');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('trip_id')
                    ->label(__('ui.trip'))
                    ->relationship('trip', 'title')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('channel')
                    ->label(__('ui.channel'))
                    ->options([
                        'email' => 'Email',
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
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('message')
                    ->label(__('ui.message'))
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('trip.title')
                    ->label(__('ui.trip'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('ui.channel'))
                    ->badge(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('ui.scheduled_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'sent' => 'success',
                        'failed' => 'danger',
                    }),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReminders::route('/'),
            'create' => Pages\CreateReminder::route('/create'),
            'edit' => Pages\EditReminder::route('/{record}/edit'),
        ];
    }
}
