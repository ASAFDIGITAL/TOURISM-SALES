<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\AttachmentResource\Pages;
use App\Models\Attachment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';

    public static function getModelLabel(): string
    {
        return __('ui.attachment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.attachments');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.attachments');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('trip_id')
                    ->label(__('ui.trip'))
                    ->relationship('trip', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label(__('ui.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label(__('ui.type'))
                    ->options([
                        'passport' => 'Passport',
                        'visa' => 'Visa',
                        'ticket' => 'Flight Ticket',
                        'voucher' => 'Hotel Voucher',
                        'insurance' => 'Insurance',
                        'other' => 'Other',
                    ])
                    ->required(),
                Forms\Components\FileUpload::make('path')
                    ->label(__('ui.file'))
                    ->directory('attachments')
                    ->required()
                    ->openable()
                    ->downloadable(),
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
                Tables\Columns\TextColumn::make('trip.customer.name')
                    ->label(__('ui.customer')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('ui.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}")),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.paid_at'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListAttachments::route('/'),
            'create' => Pages\CreateAttachment::route('/create'),
            'edit' => Pages\EditAttachment::route('/{record}/edit'),
        ];
    }
}
