<?php

namespace App\Filament\Agent\Resources\TripResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('ui.attachments');
    }

    public static function getModelLabel(): string
    {
        return __('ui.attachment');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('ui.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->label(__('ui.type'))
                    ->options([
                        'passport' => __('ui.passport'),
                        'visa' => __('ui.visa'),
                        'ticket' => __('ui.ticket'),
                        'voucher' => __('ui.voucher'),
                        'insurance' => __('ui.insurance'),
                        'other' => __('ui.other'),
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('ui.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}")),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.date_uploaded'))
                    ->dateTime()
                    ->sortable(),
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
