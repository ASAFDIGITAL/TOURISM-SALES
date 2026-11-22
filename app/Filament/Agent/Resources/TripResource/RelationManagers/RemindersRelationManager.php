<?php

namespace App\Filament\Agent\Resources\TripResource\RelationManagers;

use App\Services\TripMessageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('ui.reminders');
    }

    public static function getModelLabel(): string
    {
        return __('ui.reminder');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->tenant?->hasRemindersEnabled() ?? true;
    }

    public function form(Form $form): Form
    {
        $ownerTrip = $this->getOwnerRecord();

        return $form
            ->schema([
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
                    ->options(fn (): array => \App\Filament\Agent\Resources\ReminderResource::getAllowedChannelOptions())
                    ->default(fn (): ?string => array_key_first(\App\Filament\Agent\Resources\ReminderResource::getAllowedChannelOptions()))
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
                    ->afterStateUpdated(function (?string $state, Set $set) use ($ownerTrip): void {
                        if (!$state || $state === 'manual' || !$ownerTrip) {
                            return;
                        }
                        $baseDate = match ($state) {
                            'before_14' => $ownerTrip->start_date?->copy()->subDays(14),
                            'before_7' => $ownerTrip->start_date?->copy()->subDays(7),
                            'before_3' => $ownerTrip->start_date?->copy()->subDays(3),
                            'before_1' => $ownerTrip->start_date?->copy()->subDay(),
                            'day_of' => $ownerTrip->start_date?->copy(),
                            'after_1' => $ownerTrip->end_date?->copy()->addDay(),
                            default => null,
                        };
                        if ($baseDate) {
                            $set('scheduled_at', $baseDate->setTime(9, 0)->format('Y-m-d H:i:s'));
                        }
                    }),
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
                    ->default(fn () => $ownerTrip?->tenant?->language ?? 'he')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) use ($ownerTrip): void {
                        $tpl = $get('quick_template');
                        if (!$tpl || !$ownerTrip) {
                            return;
                        }
                        $built = app(TripMessageService::class)->buildMessage($ownerTrip, $tpl, $get('message_language') ?: 'he');
                        $set('message', $built['body']);
                    }),
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
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($ownerTrip): void {
                        if (!$state || !$ownerTrip) {
                            return;
                        }
                        $built = app(TripMessageService::class)->buildMessage($ownerTrip, $state, $get('message_language') ?: 'he');
                        $set('message', $built['body']);
                    }),
                Forms\Components\Select::make('status')
                    ->label(__('ui.status'))
                    ->options([
                        'pending' => __('ui.pending'),
                        'sent' => __('ui.sent'),
                        'failed' => __('ui.failed'),
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\Textarea::make('message')
                    ->label(__('ui.message'))
                    ->rows(5)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('ui.channel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'email' => __('ui.email'),
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('ui.scheduled_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'sent' => 'success',
                        'failed' => 'danger',
                    }),
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
