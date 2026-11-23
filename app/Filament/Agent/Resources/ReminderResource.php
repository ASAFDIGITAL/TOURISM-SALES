<?php

namespace App\Filament\Agent\Resources;

use App\Filament\Agent\Resources\ReminderResource\Pages;
use App\Models\Reminder;
use App\Models\Trip;
use App\Services\TripMessageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
        $all = [
            'push' => __('ui.channel_push'),
            'email' => __('ui.email'),
            'sms' => 'SMS (InforU)',
            'whatsapp' => 'WhatsApp',
        ];
        $allowed = auth()->user()?->getActiveTenant()?->getAllowedChannels() ?? array_keys($all);

        return array_intersect_key($all, array_flip($allowed));
    }

    public static function form(Form $form): Form
    {
        $applyTiming = function (Get $get, Set $set): void {
            $tripId = $get('trip_id');
            $preset = $get('quick_timing');
            if (!$tripId || !$preset || $preset === 'manual') {
                return;
            }
            $trip = Trip::find($tripId);
            if (!$trip) {
                return;
            }
            $baseDate = match ($preset) {
                'before_14' => $trip->start_date?->copy()->subDays(14),
                'before_7' => $trip->start_date?->copy()->subDays(7),
                'before_3' => $trip->start_date?->copy()->subDays(3),
                'before_1' => $trip->start_date?->copy()->subDay(),
                'day_of' => $trip->start_date?->copy(),
                'after_1' => $trip->end_date?->copy()->addDay(),
                default => null,
            };
            if ($baseDate) {
                $set('scheduled_at', $baseDate->setTime(9, 0)->format('Y-m-d H:i:s'));
            }
        };

        $applyTemplate = function (Get $get, Set $set): void {
            $tripId = $get('trip_id');
            $template = $get('quick_template');
            if (!$tripId || !$template) {
                return;
            }
            $trip = Trip::with(['customer', 'tenant', 'payments'])->find($tripId);
            if (!$trip) {
                return;
            }
            $lang = $get('message_language') ?: ($trip->tenant?->language ?? 'he');
            $built = app(TripMessageService::class)->buildMessage($trip, $template, $lang);
            $set('message', $built['body']);
        };

        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('trip_id')
                            ->label(__('ui.trip'))
                            ->relationship('trip', 'title')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated($applyTiming),

                        Forms\Components\Select::make('category')
                            ->label(__('ui.reminder_category'))
                            ->options([
                                'trip' => __('ui.category_trip'),
                                'payment' => __('ui.category_payment'),
                                'general' => __('ui.category_general'),
                            ])
                            ->default('trip')
                            ->required(),

                        Forms\Components\Select::make('recipient_type')
                            ->label(__('ui.recipient_type'))
                            ->options([
                                'agent' => __('ui.recipient_agent'),
                                'customer' => __('ui.recipient_customer'),
                                'both' => __('ui.recipient_both'),
                            ])
                            ->default('both')
                            ->required(),

                        Forms\Components\Select::make('channel')
                            ->label(__('ui.channel'))
                            ->options(fn (): array => self::getAllowedChannelOptions())
                            ->default(fn (): ?string => array_key_first(self::getAllowedChannelOptions()))
                            ->required(),

                        Forms\Components\Select::make('quick_timing')
                            ->label(__('ui.quick_timing'))
                            ->options([
                                'manual' => __('ui.timing_manual'),
                                'before_14' => __('ui.timing_before_14'),
                                'before_7' => __('ui.timing_before_7'),
                                'before_3' => __('ui.timing_before_3'),
                                'before_1' => __('ui.timing_before_1'),
                                'day_of' => __('ui.timing_day_of'),
                                'after_1' => __('ui.timing_after_1'),
                            ])
                            ->default('manual')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated($applyTiming),

                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label(__('ui.scheduled_at'))
                            ->required(),

                        Forms\Components\Select::make('message_language')
                            ->label(__('ui.message_language'))
                            ->options([
                                'he' => 'עברית',
                                'ar' => 'العربية',
                                'en' => 'English',
                            ])
                            ->default(fn () => auth()->user()?->getActiveTenant()?->language ?? 'he')
                            ->required()
                            ->live()
                            ->afterStateUpdated($applyTemplate),

                        Forms\Components\Select::make('quick_template')
                            ->label(__('ui.quick_template'))
                            ->options([
                                TripMessageService::TEMPLATE_PAYMENT_REMINDER => __('ui.msg_payment_reminder'),
                                TripMessageService::TEMPLATE_PRE_TRIP_REMINDER => __('ui.msg_pre_trip_reminder'),
                                TripMessageService::TEMPLATE_TRIP_SUMMARY => __('ui.msg_trip_summary'),
                            ])
                            ->placeholder(__('ui.select_template_optional'))
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated($applyTemplate),

                        Forms\Components\Select::make('status')
                            ->label(__('ui.status'))
                            ->options([
                                'pending' => __('ui.pending'),
                                'sent' => __('ui.sent'),
                                'failed' => __('ui.failed'),
                            ])
                            ->default('pending')
                            ->required(),
                    ]),

                Forms\Components\Textarea::make('message')
                    ->label(__('ui.message'))
                    ->rows(6)
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
                Tables\Columns\TextColumn::make('category')
                    ->label(__('ui.reminder_category'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __("ui.category_" . ($state ?: 'trip')))
                    ->color(fn (?string $state): string => match ($state) {
                        'payment' => 'warning',
                        'trip' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('recipient_type')
                    ->label(__('ui.recipient_type'))
                    ->formatStateUsing(fn (?string $state): string => __("ui.recipient_" . ($state ?: 'agent'))),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('ui.channel'))
                    ->badge(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('ui.scheduled_at'))
                    ->dateTime('d/m/Y H:i')
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
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('ui.reminder_category'))
                    ->options([
                        'trip' => __('ui.category_trip'),
                        'payment' => __('ui.category_payment'),
                        'general' => __('ui.category_general'),
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'pending' => __('ui.pending'),
                        'sent' => __('ui.sent'),
                        'failed' => __('ui.failed'),
                    ]),
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
            ->defaultSort('scheduled_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
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
