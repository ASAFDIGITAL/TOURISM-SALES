<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    public static function getNavigationLabel(): string
    {
        return __('ui.agents');
    }

    public static function getModelLabel(): string
    {
        return __('ui.agent');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.agents');
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
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label(__('ui.phone'))
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'active' => __('ui.active'),
                        'suspended' => __('ui.suspended'),
                        'closed' => __('ui.closed'),
                    ])
                    ->required()
                    ->default('active'),
                Forms\Components\TextInput::make('receipt_prefix')
                    ->label(__('ui.receipt_prefix'))
                    ->default('REC')
                    ->required(),
                Forms\Components\TextInput::make('receipt_next_number')
                    ->label(__('ui.next_receipt_number'))
                    ->numeric()
                    ->default(1001)
                    ->required(),
                Forms\Components\FileUpload::make('logo_path')
                    ->label(__('ui.logo') ?? 'Logo')
                    ->disk('public')
                    ->directory('logos')
                    ->image()
                    ->openable(),
                Forms\Components\Select::make('pdf_header_theme')
                    ->label(__('ui.pdf_header_theme'))
                    ->options([
                        'dark' => __('ui.pdf_header_dark'),
                        'light' => __('ui.pdf_header_light'),
                    ])
                    ->default('dark')
                    ->required(),
                Forms\Components\Select::make('currency')
                    ->label(__('ui.currency'))
                    ->options([
                        'USD' => 'USD ($)',
                        'ILS' => 'ILS (₪)',
                        'EUR' => 'EUR (€)',
                        'GBP' => 'GBP (£)',
                    ])
                    ->default('USD')
                    ->required(),
                Forms\Components\Select::make('language')
                    ->label(__('ui.language'))
                    ->options([
                        'he' => 'Hebrew',
                        'ar' => 'Arabic',
                        'en' => 'English',
                    ])
                    ->default('he')
                    ->required(),
                Forms\Components\Section::make(__('ui.subscription'))
                    ->schema([
                        Forms\Components\Select::make('plan_id')
                            ->label(__('ui.plan'))
                            ->relationship('plan', 'name_he')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Forms\Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $plan = \App\Models\Plan::find($state);
                                if ($plan) {
                                    $set('reminders_enabled', (bool) $plan->reminders_enabled);
                                    $set('allowed_channels', $plan->getAllowedChannels());
                                    $set('subscription_amount', $plan->monthly_price);
                                }
                            }),
                        Forms\Components\TextInput::make('extra_seats')
                            ->label(__('ui.extra_seats_label'))
                            ->helperText(__('ui.extra_seats_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\Toggle::make('is_trial')
                            ->label(__('ui.is_trial_label'))
                            ->default(false)
                            ->live(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label(__('ui.trial_ends_at'))
                            ->native(false)
                            ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('is_trial')),
                        Forms\Components\Select::make('subscription_type')
                            ->label(__('ui.subscription_type'))
                            ->options([
                                'recurring' => __('ui.subscription_recurring'),
                                'lifetime' => __('ui.subscription_lifetime'),
                            ])
                            ->default('recurring')
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('joined_at')
                            ->label(__('ui.joined_at'))
                            ->native(false),
                        Forms\Components\DatePicker::make('expires_at')
                            ->label(__('ui.expires_at'))
                            ->native(false)
                            ->visible(fn (\Filament\Forms\Get $get): bool => $get('subscription_type') !== 'lifetime'),
                        Forms\Components\TextInput::make('subscription_amount')
                            ->label(__('ui.subscription_amount'))
                            ->numeric()
                            ->prefix(match(auth()->user()->currency ?? 'USD') {
                                'ILS' => '₪',
                                'EUR' => '€',
                                'GBP' => '£',
                                default => '$',
                            }),
                        Forms\Components\TextInput::make('renewal_payment_url')
                            ->label(__('ui.renewal_payment_url'))
                            ->url()
                            ->placeholder('https://...')
                            ->maxLength(500)
                            ->visible(fn (\Filament\Forms\Get $get): bool => $get('subscription_type') !== 'lifetime')
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make(__('ui.reminders_permissions_section'))
                    ->description(__('ui.reminders_permissions_desc'))
                    ->schema([
                        Forms\Components\Toggle::make('reminders_enabled')
                            ->label(__('ui.reminders_enabled_label'))
                            ->default(true)
                            ->live(),
                        Forms\Components\CheckboxList::make('allowed_channels')
                            ->label(__('ui.allowed_channels_label'))
                            ->options([
                                'push' => __('ui.channel_push'),
                                'email' => __('ui.email'),
                                'whatsapp' => 'WhatsApp',
                                'sms' => 'SMS (InforU)',
                            ])
                            ->default(Tenant::ALL_CHANNELS)
                            ->columns(2)
                            ->visible(fn (\Filament\Forms\Get $get): bool => (bool) $get('reminders_enabled')),
                    ]),
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
                Tables\Columns\TextColumn::make('plan.name_he')
                    ->label(__('ui.plan'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('seats_usage')
                    ->label(__('ui.team_seats'))
                    ->state(fn (Tenant $record): string => $record->getActiveUsersCount() . ' / ' . $record->getMaxUsers())
                    ->badge()
                    ->color(fn (Tenant $record): string => $record->canAddMoreUsers() ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'closed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('joined_at')
                    ->label(__('ui.joined_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('ui.expires_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label(__('ui.days_remaining'))
                    ->state(function (Tenant $record): string|int|null {
                        if ($record->isLifetime()) {
                            return __('ui.subscription_lifetime_short');
                        }
                        if (!$record->expires_at) {
                            return null;
                        }
                        return now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($record->expires_at), false);
                    })
                    ->badge()
                    ->color(fn ($state, Tenant $record) => match (true) {
                        $record->isLifetime() => 'success',
                        $state === null => 'gray',
                        (int) $state <= 0 => 'danger',
                        (int) $state <= 30 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_amount')
                    ->label(__('ui.subscription_amount'))
                    ->money(fn() => auth()->user()->currency ?? 'USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('enter_system')
                    ->label(__('ui.enter_system'))
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('info')
                    ->action(function (Tenant $record) {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);
                        auth()->user()->startImpersonatingTenant($record);

                        return redirect('/agent');
                    }),
                Tables\Actions\EditAction::make(),
                \App\Filament\Actions\SendAdminNotificationAction::makeTableAction(),
                \App\Filament\Actions\SendTenantRenewalAction::makeTableAction(),
                Tables\Actions\Action::make('renew')
                    ->label(__('ui.renew_subscription'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (Tenant $record): bool => !$record->isLifetime())
                    ->form([
                        Forms\Components\TextInput::make('months')
                            ->label('Extra Months')
                            ->numeric()
                            ->default(12)
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label(__('ui.subscription_amount'))
                            ->numeric()
                            ->prefix(match(auth()->user()->currency ?? 'USD') {
                                'ILS' => '₪',
                                'EUR' => '€',
                                'GBP' => '£',
                                default => '$',
                            })
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        $currentExpiry = $record->expires_at ? \Carbon\Carbon::parse($record->expires_at) : now();
                        if ($currentExpiry->isPast()) {
                            $currentExpiry = now();
                        }
                        
                        $record->update([
                            'expires_at' => $currentExpiry->addMonths($data['months']),
                            'subscription_amount' => $record->subscription_amount + $data['amount'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('ui.renewal_success'))
                            ->send();
                    })
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
