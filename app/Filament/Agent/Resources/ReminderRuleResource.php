<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\ReminderRuleResource\Pages;
use App\Models\ReminderRule;
use App\Services\TripMessageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReminderRuleResource extends Resource
{
    protected static ?string $model = ReminderRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('ui.reminder_rules');
    }

    public static function getModelLabel(): string
    {
        return __('ui.reminder_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.reminder_rules');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->getActiveTenant()?->hasRemindersEnabled() ?? true)
            && ($user?->hasAgentPermission(\App\Models\User::PERM_MANAGE_REMINDERS) ?? false);
    }

    /**
     * @return array<string, string>
     */
    public static function getAllowedChannelOptions(): array
    {
        $tenant = auth()->user()?->getActiveTenant();
        $all = [
            'push' => __('ui.channel_push'),
            'email' => __('ui.email'),
            'sms' => 'SMS (InforU)',
        ];
        $allowed = $tenant ? $tenant->getAllowedChannels() : ['push', 'email', 'sms'];

        return array_intersect_key($all, array_flip($allowed));
    }

    public static function formatChannelsForTable(ReminderRule $record): string
    {
        $channels = $record->getSelectedChannels();
        $parts = [];

        foreach ($channels as $ch) {
            $parts[] = match ($ch) {
                'push' => 'Push',
                'email' => __('ui.email'),
                'sms' => 'SMS',
                default => $ch,
            };
        }

        return count($parts) > 0 ? implode(' + ', $parts) : '-';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('ui.rule_condition_section'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('ui.rule_name'))
                            ->placeholder(__('ui.rule_name_placeholder'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('trigger_type')
                            ->label(__('ui.trigger_type'))
                            ->options([
                                ReminderRule::TRIGGER_DAYS_BEFORE_TRIP => __('ui.trigger_days_before_trip'),
                                ReminderRule::TRIGGER_DAYS_AFTER_TRIP => __('ui.trigger_days_after_trip'),
                                ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE => __('ui.trigger_days_before_payment_due'),
                                ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID => __('ui.trigger_days_after_booking_unpaid'),
                            ])
                            ->required()
                            ->default(ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE)
                            ->live(),

                        Forms\Components\TextInput::make('days_offset')
                            ->label(__('ui.days_offset'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->default(7)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('ui.active'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('ui.rule_delivery_section'))
                    ->schema([
                        Forms\Components\CheckboxList::make('recipient_type')
                            ->label(__('ui.recipient_type'))
                            ->options([
                                'agent' => __('ui.recipient_agent'),
                                'customer' => __('ui.recipient_customer'),
                            ])
                            ->default(['agent', 'customer'])
                            ->formatStateUsing(function ($state): array {
                                if (is_array($state)) {
                                    return $state;
                                }

                                return match ($state) {
                                    'both' => ['agent', 'customer'],
                                    'agent' => ['agent'],
                                    'customer' => ['customer'],
                                    default => ['agent', 'customer'],
                                };
                            })
                            ->dehydrateStateUsing(function ($state): string {
                                $arr = is_array($state) ? array_values(array_unique($state)) : [];
                                $hasAgent = in_array('agent', $arr, true);
                                $hasCustomer = in_array('customer', $arr, true);

                                if ($hasAgent && $hasCustomer) {
                                    return 'both';
                                }

                                return $hasCustomer ? 'customer' : 'agent';
                            })
                            ->required()
                            ->minItems(1),

                        Forms\Components\CheckboxList::make('channel')
                            ->label(__('ui.channel'))
                            ->options(fn (): array => self::getAllowedChannelOptions())
                            ->default(fn (): array => array_keys(self::getAllowedChannelOptions()))
                            ->formatStateUsing(function ($state): array {
                                $allowed = array_keys(self::getAllowedChannelOptions());

                                if (is_array($state)) {
                                    return array_values(array_intersect($state, $allowed));
                                }

                                $raw = trim((string) $state);
                                if ($raw === '' || $raw === 'all') {
                                    return $allowed;
                                }

                                $parsed = array_values(array_filter(array_map('trim', explode(',', $raw))));

                                return array_values(array_intersect($parsed, $allowed));
                            })
                            ->dehydrateStateUsing(function ($state): string {
                                $arr = is_array($state) ? array_values(array_unique($state)) : [];

                                return implode(',', $arr);
                            })
                            ->required()
                            ->minItems(1),

                        Forms\Components\Select::make('template_type')
                            ->label(__('ui.message_type'))
                            ->options([
                                TripMessageService::TEMPLATE_PAYMENT_REMINDER => __('ui.msg_payment_reminder'),
                                TripMessageService::TEMPLATE_TRIP_SUMMARY => __('ui.msg_trip_summary'),
                                TripMessageService::TEMPLATE_PRE_TRIP_REMINDER => __('ui.msg_pre_trip_reminder'),
                                TripMessageService::TEMPLATE_CUSTOM => __('ui.msg_custom'),
                            ])
                            ->default(TripMessageService::TEMPLATE_PAYMENT_REMINDER)
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('message_language')
                            ->label(__('ui.message_language'))
                            ->options([
                                'he' => 'עברית',
                                'ar' => 'العربية',
                                'en' => 'English',
                            ])
                            ->default(fn () => auth()->user()?->getActiveTenant()?->language ?? 'he')
                            ->required(),

                        Forms\Components\Textarea::make('custom_message')
                            ->label(__('ui.message'))
                            ->rows(5)
                            ->helperText(__('ui.placeholders_help'))
                            ->visible(fn (Get $get): bool => $get('template_type') === TripMessageService::TEMPLATE_CUSTOM)
                            ->required(fn (Get $get): bool => $get('template_type') === TripMessageService::TEMPLATE_CUSTOM)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('logs')
            ->withCount([
                'logs as monthly_logs_count' => fn ($q) => $q->whereBetween('sent_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ]),
            ])
            ->withMax('logs', 'sent_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.rule_name'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('trigger_type')
                    ->label(__('ui.trigger_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.trigger_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                        ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('days_offset')
                    ->label(__('ui.days_offset'))
                    ->suffix(' ' . __('ui.days_unit'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient_type')
                    ->label(__('ui.recipient_type'))
                    ->formatStateUsing(fn (string $state): string => __("ui.recipient_{$state}")),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('ui.channel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state, ReminderRule $record): string => self::formatChannelsForTable($record)),
                Tables\Columns\TextColumn::make('monthly_logs_count')
                    ->label(__('ui.sent_this_month'))
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('logs_count')
                    ->label(__('ui.total_sent_count'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('logs_max_sent_at')
                    ->label(__('ui.last_sent_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('ui.active')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_logs')
                    ->label(__('ui.view_rule_logs'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->modalHeading(fn (ReminderRule $record): string => __('ui.rule_logs_modal_title', ['name' => $record->name]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('ui.close'))
                    ->modalContent(function (ReminderRule $record) {
                        $logs = $record->logs()
                            ->with(['trip.customer', 'reminder'])
                            ->orderByDesc('sent_at')
                            ->limit(50)
                            ->get();

                        return view('filament.agent.components.rule-logs-modal', [
                            'logs' => $logs,
                        ]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReminderRules::route('/'),
            'create' => Pages\CreateReminderRule::route('/create'),
            'edit' => Pages\EditReminderRule::route('/{record}/edit'),
        ];
    }
}
