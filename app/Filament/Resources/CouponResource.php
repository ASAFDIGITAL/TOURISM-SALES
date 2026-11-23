<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('ui.coupons');
    }

    public static function getModelLabel(): string
    {
        return __('ui.coupon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.coupons');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.coupon_details_section'))
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label(__('ui.coupon_code'))
                            ->placeholder('WELCOME20')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(64)
                            ->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state))),

                        Forms\Components\TextInput::make('name')
                            ->label(__('ui.description'))
                            ->placeholder(__('ui.coupon_name_placeholder'))
                            ->maxLength(120),

                        Forms\Components\Select::make('discount_type')
                            ->label(__('ui.discount_type'))
                            ->options([
                                Coupon::TYPE_PERCENT => __('ui.discount_type_percent'),
                                Coupon::TYPE_FIXED => __('ui.discount_type_fixed'),
                            ])
                            ->default(Coupon::TYPE_PERCENT)
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label(__('ui.discount_value'))
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->suffix(fn (Forms\Get $get): string => $get('discount_type') === Coupon::TYPE_FIXED ? '₪' : '%'),

                        Forms\Components\Select::make('plan_id')
                            ->label(__('ui.coupon_plan_restriction'))
                            ->relationship('plan', 'name_he')
                            ->placeholder(__('ui.all_plans_option'))
                            ->preload(),

                        Forms\Components\Select::make('billing_cycle')
                            ->label(__('ui.billing_cycle_label'))
                            ->options([
                                'monthly' => __('ui.billing_monthly'),
                                'yearly' => __('ui.billing_yearly'),
                            ])
                            ->placeholder(__('ui.all_cycles_option')),

                        Forms\Components\TextInput::make('max_uses')
                            ->label(__('ui.coupon_max_uses'))
                            ->numeric()
                            ->minValue(1)
                            ->placeholder(__('ui.unlimited_quota')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->default(true),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label(__('ui.starts_at'))
                            ->native(false),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('ui.expires_at'))
                            ->native(false),

                        Forms\Components\TextInput::make('upay_override_url')
                            ->label(__('ui.coupon_upay_url_label'))
                            ->helperText(__('ui.coupon_upay_url_helper'))
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('ui.coupon_code'))
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.description'))
                    ->placeholder('-')
                    ->searchable(),

                Tables\Columns\TextColumn::make('discount_display')
                    ->label(__('ui.discount_value'))
                    ->state(fn (Coupon $record): string => $record->getFormattedValue())
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('plan.name_he')
                    ->label(__('ui.plan'))
                    ->placeholder(__('ui.all_plans_option'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('usage')
                    ->label(__('ui.coupon_uses'))
                    ->state(fn (Coupon $record): string => $record->max_uses !== null
                        ? "{$record->used_count} / {$record->max_uses}"
                        : "{$record->used_count} / ∞")
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('ui.expires_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('∞')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
