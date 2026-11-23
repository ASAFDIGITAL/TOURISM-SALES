<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('ui.plans_and_pricing');
    }

    public static function getModelLabel(): string
    {
        return __('ui.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.plans_and_pricing');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.plan_identity_section'))
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(64),
                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('ui.sort_order'))
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('name_he')
                            ->label(__('ui.name') . ' (עברית)')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('name_en')
                            ->label(__('ui.name') . ' (English)')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('name_ar')
                            ->label(__('ui.name') . ' (العربية)')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('description_he')
                            ->label(__('ui.description') . ' (עברית)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description_en')
                            ->label(__('ui.description') . ' (English)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description_ar')
                            ->label(__('ui.description') . ' (العربية)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('ui.plan_pricing_and_seats_section'))
                    ->schema([
                        Forms\Components\TextInput::make('monthly_price')
                            ->label(__('ui.monthly_price'))
                            ->numeric()
                            ->required()
                            ->default(199),
                        Forms\Components\TextInput::make('yearly_price')
                            ->label(__('ui.yearly_price'))
                            ->numeric()
                            ->required()
                            ->default(1990),
                        Forms\Components\Select::make('currency')
                            ->label(__('ui.currency'))
                            ->options([
                                'ILS' => '₪ (ILS)',
                                'USD' => '$ (USD)',
                                'EUR' => '€ (EUR)',
                            ])
                            ->default('ILS')
                            ->required(),
                        Forms\Components\TextInput::make('trial_days')
                            ->label(__('ui.trial_days'))
                            ->numeric()
                            ->default(14)
                            ->required(),
                        Forms\Components\TextInput::make('included_users')
                            ->label(__('ui.included_users'))
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('extra_user_price')
                            ->label(__('ui.extra_user_price'))
                            ->numeric()
                            ->default(49)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('ui.plan_features_section'))
                    ->schema([
                        Forms\Components\Toggle::make('reminders_enabled')
                            ->label(__('ui.reminders_enabled_label'))
                            ->default(true),
                        Forms\Components\Toggle::make('can_use_employee_reports')
                            ->label(__('ui.employee_performance_reports'))
                            ->default(false),
                        Forms\Components\Toggle::make('is_featured')
                            ->label(__('ui.featured_plan_badge'))
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->default(true),
                        Forms\Components\CheckboxList::make('allowed_channels')
                            ->label(__('ui.allowed_channels_label'))
                            ->options([
                                'push' => __('ui.channel_push'),
                                'email' => __('ui.channel_email'),
                                'whatsapp' => __('ui.channel_whatsapp'),
                                'sms' => __('ui.channel_sms'),
                            ])
                            ->default(['push', 'email', 'whatsapp', 'sms'])
                            ->columns(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('monthly_email_limit')
                            ->label(__('ui.monthly_email_limit_label'))
                            ->helperText(__('ui.leave_empty_unlimited'))
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('100'),
                        Forms\Components\TextInput::make('monthly_sms_limit')
                            ->label(__('ui.monthly_sms_limit_label'))
                            ->helperText(__('ui.leave_empty_unlimited'))
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('50'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_he')
                    ->label(__('ui.plan'))
                    ->description(fn (Plan $record): string => $record->slug)
                    ->weight('bold')
                    ->searchable(['name_he', 'name_en', 'slug']),
                Tables\Columns\TextColumn::make('monthly_price')
                    ->label(__('ui.monthly_price'))
                    ->formatStateUsing(fn ($state, Plan $record): string => $record->getCurrencySymbol() . number_format((float) $state, 0)),
                Tables\Columns\TextColumn::make('included_users')
                    ->label(__('ui.included_users'))
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('extra_user_price')
                    ->label(__('ui.extra_user_price'))
                    ->formatStateUsing(fn ($state, Plan $record): string => '+' . $record->getCurrencySymbol() . number_format((float) $state, 0)),
                Tables\Columns\TextColumn::make('monthly_email_limit')
                    ->label(__('ui.monthly_email_limit_short'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? (string) $state : '∞')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('monthly_sms_limit')
                    ->label(__('ui.monthly_sms_limit_short'))
                    ->formatStateUsing(fn ($state): string => $state !== null ? (string) $state : '∞')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('trial_days')
                    ->label(__('ui.trial_days'))
                    ->suffix(' ' . __('ui.days_short')),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('ui.featured_plan_badge'))
                    ->boolean(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
