<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemAnnouncementResource\Pages;
use App\Models\SystemAnnouncement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemAnnouncementResource extends Resource
{
    protected static ?string $model = SystemAnnouncement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('ui.system_announcements');
    }

    public static function getModelLabel(): string
    {
        return __('ui.system_announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.system_announcements');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.announcement_content_section'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('ui.title'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label(__('ui.type'))
                            ->options([
                                'info' => __('ui.announcement_info'),
                                'warning' => __('ui.announcement_warning'),
                                'success' => __('ui.announcement_success'),
                                'danger' => __('ui.announcement_danger'),
                            ])
                            ->default('info')
                            ->required(),

                        Forms\Components\Textarea::make('body')
                            ->label(__('ui.message'))
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('ui.announcement_target_schedule_section'))
                    ->schema([
                        Forms\Components\Select::make('target_type')
                            ->label(__('ui.announcement_target'))
                            ->options([
                                'all' => __('ui.target_all_users'),
                                'tenant' => __('ui.target_specific_agent'),
                                'user' => __('ui.target_specific_user'),
                            ])
                            ->default('all')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('tenant_id')
                            ->label(__('ui.agent'))
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('target_type') === 'tenant')
                            ->required(fn (Get $get): bool => $get('target_type') === 'tenant'),

                        Forms\Components\Select::make('user_id')
                            ->label(__('ui.user'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('target_type') === 'user')
                            ->required(fn (Get $get): bool => $get('target_type') === 'user'),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('ui.announcement_starts_at'))
                            ->helperText(__('ui.leave_empty_immediate'))
                            ->native(false),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label(__('ui.announcement_ends_at'))
                            ->helperText(__('ui.leave_empty_no_expiry'))
                            ->native(false)
                            ->afterOrEqual('starts_at'),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_dismissible')
                            ->label(__('ui.announcement_dismissible'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ui.title'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('ui.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.announcement_{$state}"))
                    ->color(fn (string $state): string => $state),

                Tables\Columns\TextColumn::make('target_type')
                    ->label(__('ui.announcement_target'))
                    ->formatStateUsing(function (SystemAnnouncement $record): string {
                        return match ($record->target_type) {
                            'tenant' => __('ui.agent') . ': ' . ($record->tenant?->name ?? '-'),
                            'user' => __('ui.user') . ': ' . ($record->user?->name ?? '-'),
                            default => __('ui.target_all_users'),
                        };
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label(__('ui.announcement_starts_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label(__('ui.announcement_ends_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('live_status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.announcement_status_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        'expired' => 'gray',
                        default => 'danger',
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemAnnouncements::route('/'),
            'create' => Pages\CreateSystemAnnouncement::route('/create'),
            'edit' => Pages\EditSystemAnnouncement::route('/{record}/edit'),
        ];
    }
}
