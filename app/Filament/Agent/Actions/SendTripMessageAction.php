<?php

namespace App\Filament\Agent\Actions;

use App\Models\Trip;
use App\Services\TripMessageService;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;

class SendTripMessageAction
{
    public static function makeTableAction(
        string $name = 'send_message',
        string $defaultTemplate = TripMessageService::TEMPLATE_TRIP_SUMMARY,
        ?string $label = null,
    ): TableAction {
        return TableAction::make($name)
            ->label($label ?? __('ui.send_message'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('info')
            ->visible(fn (Trip $record): bool => count(self::allowedSendChannels($record)) > 0)
            ->modalHeading(__('ui.send_message_to_customer'))
            ->modalSubmitActionLabel(__('ui.send_now'))
            ->modalWidth('2xl')
            ->fillForm(fn (Trip $record): array => self::getInitialFormData($record, $defaultTemplate))
            ->form(fn (Trip $record): array => self::getFormSchema($record))
            ->action(function (Trip $record, array $data, $livewire): void {
                self::handleSend($record, $data, $livewire);
            });
    }

    public static function makeHeaderAction(
        string $name = 'send_message',
        string $defaultTemplate = TripMessageService::TEMPLATE_TRIP_SUMMARY,
    ): PageAction {
        return PageAction::make($name)
            ->label(__('ui.send_message'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('info')
            ->visible(fn ($livewire): bool => count(self::allowedSendChannels($livewire->record)) > 0)
            ->modalHeading(__('ui.send_message_to_customer'))
            ->modalSubmitActionLabel(__('ui.send_now'))
            ->modalWidth('2xl')
            ->fillForm(fn ($livewire): array => self::getInitialFormData($livewire->record, $defaultTemplate))
            ->form(fn ($livewire): array => self::getFormSchema($livewire->record))
            ->action(function (array $data, $livewire): void {
                self::handleSend($livewire->record, $data, $livewire);
            });
    }

    /**
     * @return array<string, string>
     */
    private static function allowedSendChannels(Trip $trip): array
    {
        $tenant = $trip->tenant;
        if ($tenant && !$tenant->hasRemindersEnabled()) {
            return [];
        }

        $options = [];
        if (!$tenant || $tenant->canUseChannel('whatsapp')) {
            $options['whatsapp'] = 'WhatsApp';
        }
        if (!$tenant || $tenant->canUseChannel('email')) {
            $options['email'] = __('ui.email');
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getInitialFormData(Trip $trip, string $defaultTemplate): array
    {
        $tenantLang = $trip->tenant?->language ?? 'he';
        $defaultLang = in_array($tenantLang, ['he', 'ar', 'en'], true) ? $tenantLang : 'he';
        $built = app(TripMessageService::class)->buildMessage($trip, $defaultTemplate, $defaultLang);
        $allowed = self::allowedSendChannels($trip);
        $defaultChannel = (isset($allowed['whatsapp']) && filled($trip->customer?->phone))
            ? 'whatsapp'
            : (array_key_first($allowed) ?? 'email');

        return [
            'template' => $defaultTemplate,
            'message_language' => $defaultLang,
            'channel' => $defaultChannel,
            'recipient_phone' => $trip->customer?->phone ?? '',
            'recipient_email' => $trip->customer?->email ?? '',
            'sender_name' => $trip->tenant?->name ?? config('mail.from.name', 'TOURISM-SALES'),
            'subject' => $built['subject'],
            'message_body' => $built['body'],
            'attach_itinerary' => $defaultTemplate === TripMessageService::TEMPLATE_TRIP_SUMMARY,
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function getFormSchema(Trip $trip): array
    {
        $regenerate = function (Get $get, Set $set) use ($trip): void {
            $template = $get('template') ?: TripMessageService::TEMPLATE_TRIP_SUMMARY;
            $lang = $get('message_language') ?: 'he';
            $built = app(TripMessageService::class)->buildMessage($trip, $template, $lang);
            $set('subject', $built['subject']);
            $set('message_body', $built['body']);
            $set('attach_itinerary', $template === TripMessageService::TEMPLATE_TRIP_SUMMARY);
        };

        return [
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('template')
                        ->label(__('ui.message_type'))
                        ->options([
                            TripMessageService::TEMPLATE_TRIP_SUMMARY => __('ui.msg_trip_summary'),
                            TripMessageService::TEMPLATE_PAYMENT_REMINDER => __('ui.msg_payment_reminder'),
                            TripMessageService::TEMPLATE_PRE_TRIP_REMINDER => __('ui.msg_pre_trip_reminder'),
                            TripMessageService::TEMPLATE_CUSTOM => __('ui.msg_custom'),
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated($regenerate),

                    Forms\Components\Select::make('message_language')
                        ->label(__('ui.message_language'))
                        ->options([
                            'he' => 'עברית',
                            'ar' => 'العربية',
                            'en' => 'English',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated($regenerate),
                ]),

            Forms\Components\Radio::make('channel')
                ->label(__('ui.channel'))
                ->options(fn (): array => self::allowedSendChannels($trip))
                ->inline()
                ->inlineLabel(false)
                ->required()
                ->live(),

            Forms\Components\TextInput::make('recipient_phone')
                ->label(__('ui.phone'))
                ->tel()
                ->visible(fn (Get $get): bool => $get('channel') === 'whatsapp')
                ->required(fn (Get $get): bool => $get('channel') === 'whatsapp'),

            Forms\Components\TextInput::make('recipient_email')
                ->label(__('ui.email'))
                ->email()
                ->visible(fn (Get $get): bool => $get('channel') === 'email')
                ->required(fn (Get $get): bool => $get('channel') === 'email'),

            Forms\Components\TextInput::make('sender_name')
                ->label(__('ui.email_sender_name'))
                ->visible(fn (Get $get): bool => $get('channel') === 'email')
                ->maxLength(120),

            Forms\Components\TextInput::make('subject')
                ->label(__('ui.message_subject'))
                ->visible(fn (Get $get): bool => $get('channel') === 'email')
                ->required(fn (Get $get): bool => $get('channel') === 'email')
                ->maxLength(255),

            Forms\Components\Textarea::make('message_body')
                ->label(__('ui.message'))
                ->rows(10)
                ->required()
                ->extraAttributes(fn (Get $get): array => [
                    'dir' => in_array($get('message_language'), ['he', 'ar'], true) ? 'rtl' : 'ltr',
                ]),

            Forms\Components\Toggle::make('attach_itinerary')
                ->label(__('ui.attach_itinerary_pdf'))
                ->visible(fn (Get $get): bool => $get('channel') === 'email'),
        ];
    }

    private static function handleSend(Trip $trip, array $data, $livewire): void
    {
        $service = app(TripMessageService::class);
        $channel = $data['channel'] ?? 'whatsapp';
        $body = (string) ($data['message_body'] ?? '');
        $lang = (string) ($data['message_language'] ?? 'he');

        if ($channel === 'whatsapp') {
            $phone = (string) ($data['recipient_phone'] ?? '');
            $url = $service->buildWhatsAppUrl($phone, $body);
            $service->logSentMessage($trip, 'whatsapp', $body);

            $livewire->js('window.open(' . json_encode($url) . ', "_blank")');

            Notification::make()
                ->success()
                ->title(__('ui.whatsapp_opened'))
                ->send();

            return;
        }

        $email = (string) ($data['recipient_email'] ?? '');
        $subject = (string) ($data['subject'] ?? $trip->title);
        $attachPdf = (bool) ($data['attach_itinerary'] ?? false);
        $senderName = trim((string) ($data['sender_name'] ?? '')) ?: $trip->tenant?->name;

        $service->sendEmail(
            trip: $trip,
            recipientEmail: $email,
            subject: $subject,
            body: $body,
            locale: $lang,
            attachItinerary: $attachPdf,
            senderName: $senderName,
        );

        Notification::make()
            ->success()
            ->title(__('ui.message_sent_email'))
            ->send();
    }
}
