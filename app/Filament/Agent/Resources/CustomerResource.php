<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getModelLabel(): string
    {
        return __('ui.customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.customers');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.customers');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('ui.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label(__('ui.phone'))
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('ui.email'))
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('passport_number')
                    ->label(__('ui.passport_number'))
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->label(__('ui.address'))
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label(__('ui.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('ui.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('ui.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('passport_number')
                    ->label(__('ui.passport_number'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            CustomerResource\RelationManagers\TripsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
