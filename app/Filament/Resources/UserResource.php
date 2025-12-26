<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('ui.users');
    }

    public static function getModelLabel(): string
    {
        return __('ui.user') ?? 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.users');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('ui.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('ui.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at')
                    ->label(__('ui.email_verified_at') ?? 'Email Verified At'),
                Forms\Components\TextInput::make('password')
                    ->label(__('ui.password'))
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                Forms\Components\Select::make('tenant_id')
                    ->label(__('ui.agent'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('role')
                    ->label(__('ui.role'))
                    ->options([
                        'super_admin' => __('ui.super_admin'),
                        'agent' => __('ui.agent'),
                    ])
                    ->required()
                    ->default('agent'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('ui.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label(__('ui.email_verified_at') ?? 'Email Verified At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant_id')
                    ->label(__('ui.agent'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('ui.role'))
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('sendPasswordReset')
                    ->label(__('ui.send_password_reset'))
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->sendPasswordResetNotification(
                        \Illuminate\Support\Facades\Password::getRepository()->create($record)
                    ))
                    ->successNotificationTitle(__('ui.password_reset_link_sent')),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
