<?php

namespace App\Filament\Actions;

use App\Mail\TenantRenewalMail;
use App\Models\Tenant;
use App\Services\InforuSmsService;
use App\Services\TripMessageService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Facades\Mail;

class SendTenantRenewalAction
{
    public static function makeTableAction(): TableAction
    {
        return TableAction::make('send_renewal_link')
            ->label(__('ui.send_renewal_link'))
            ->icon('heroicon-o-credit-card')
            ->color('primary')
            ->visible(fn (Tenant $record): bool => !$record->isLifetime())
            ->modalHeading(__('ui.send_renewal_modal_heading'))
            ->modalSubmitActionLabel(__('ui.send_now'))
            ->modalWidth('2xl')
            ->fillForm(fn (Tenant $record): array => self::initialData($record))
            ->form(fn (Tenant $record): array => self::formSchema($record))
            ->action(function (Tenant $record, array $data, $livewire): void {
                self::handleSend($record, $data, $livewire);
            });
    }

    /**
     * @return array{subject: string, body: string}
     */
    public static function buildRenewalMessage(Tenant $tenant, ?string $paymentUrl, ?string $amount, string $locale = 'he'): array
    {
        $expiryDate = $tenant->expires_at ? Carbon::parse($tenant->expires_at)->format('d/m/Y') : '-';
        $daysLeft = $tenant->expires_at
            ? (int) now()->startOfDay()->diffInDays(Carbon::parse($tenant->expires_at)->startOfDay(), false)
            : null;
        $currency = auth()->user()?->currency ?? ($tenant->currency ?? 'ILS');
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
        $formattedAmount = is_numeric($amount) && (float) $amount > 0
            ? $symbol . number_format((float) $amount, 2)
            : null;

        if ($locale === 'ar') {
            $subject = "تجديد اشتراك النظام — {$tenant->name}";
            $lines = [
                "مرحباً {$tenant->name}،",
                'نود تذكيرك بأن اشتراكك في نظام *TOURISM-SALES* يقترب من تاريخ الانتهاء:',
                '',
                "- *تاريخ الانتهاء:* {$expiryDate}" . ($daysLeft !== null ? " (متبقي {$daysLeft} أيام)" : ''),
            ];
            if ($formattedAmount) {
                $lines[] = "- *مبلغ التجديد:* {$formattedAmount}";
            }
            if (filled($paymentUrl)) {
                $lines[] = '';
                $lines[] = "*رابط الدفع لتجديد الاشتراك:*\n{$paymentUrl}";
            }
            $lines[] = '';
            $lines[] = 'شكراً لتعاملكم معنا، فريق TOURISM-SALES by ASAF DIGITAL';

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        if ($locale === 'en') {
            $subject = "Subscription Renewal — {$tenant->name}";
            $lines = [
                "Hello {$tenant->name},",
                'Your *TOURISM-SALES* subscription is coming up for renewal:',
                '',
                "- *Expiry Date:* {$expiryDate}" . ($daysLeft !== null ? " ({$daysLeft} days remaining)" : ''),
            ];
            if ($formattedAmount) {
                $lines[] = "- *Renewal Amount:* {$formattedAmount}";
            }
            if (filled($paymentUrl)) {
                $lines[] = '';
                $lines[] = "*Payment Link to Renew:*\n{$paymentUrl}";
            }
            $lines[] = '';
            $lines[] = 'Thank you, TOURISM-SALES by ASAF DIGITAL';

            return ['subject' => $subject, 'body' => implode("\n", $lines)];
        }

        // Hebrew default
        $subject = "חידוש מנוי למערכת — {$tenant->name}";
        $lines = [
            "שלום {$tenant->name},",
            'תזכורת ידידותית לגבי חידוש המנוי שלך במערכת *TOURISM-SALES*:',
            '',
            "- *תאריך סיום מנוי:* {$expiryDate}" . ($daysLeft !== null ? " (נותרו {$daysLeft} ימים)" : ''),
        ];
        if ($formattedAmount) {
            $lines[] = "- *סכום לחידוש:* {$formattedAmount}";
        }
        if (filled($paymentUrl)) {
            $lines[] = '';
            $lines[] = "*קישור לתשלום וחידוש המנוי:*\n{$paymentUrl}";
        }
        $lines[] = '';
        $lines[] = 'נשמח להמשיך לעמוד לשירותך! צוות TOURISM-SALES by ASAF DIGITAL';

        return ['subject' => $subject, 'body' => implode("\n", $lines)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function initialData(Tenant $tenant): array
    {
        $lang = in_array($tenant->language, ['he', 'ar', 'en'], true) ? $tenant->language : 'he';
        $paymentUrl = $tenant->renewal_payment_url ?? '';
        $amount = $tenant->subscription_amount ? (string) $tenant->subscription_amount : '';
        $built = self::buildRenewalMessage($tenant, $paymentUrl, $amount, $lang);

        return [
            'message_language' => $lang,
            'channel' => filled($tenant->phone) ? 'whatsapp' : 'email',
            'recipient_phone' => $tenant->phone ?? '',
            'recipient_email' => $tenant->email ?? '',
            'payment_url' => $paymentUrl,
            'renewal_amount' => $amount,
            'subject' => $built['subject'],
            'message_body' => $built['body'],
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function formSchema(Tenant $tenant): array
    {
        $regenerate = function (Get $get, Set $set) use ($tenant): void {
            $built = self::buildRenewalMessage(
                $tenant,
                $get('payment_url'),
                $get('renewal_amount'),
                $get('message_language') ?: 'he'
            );
            $set('subject', $built['subject']);
            $set('message_body', $built['body']);
        };

        return [
            Forms\Components\Grid::make(2)
                ->schema([
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

                    Forms\Components\TextInput::make('renewal_amount')
                        ->label(__('ui.subscription_amount'))
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated($regenerate),
                ]),

            Forms\Components\TextInput::make('payment_url')
                ->label(__('ui.renewal_payment_url'))
                ->url()
                ->placeholder('https://...')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated($regenerate),

            Forms\Components\Radio::make('channel')
                ->label(__('ui.channel'))
                ->options([
                    'whatsapp' => 'WhatsApp',
                    'email' => __('ui.email'),
                    'sms' => 'SMS (InforU)',
                ])
                ->inline()
                ->inlineLabel(false)
                ->required()
                ->live(),

            Forms\Components\TextInput::make('recipient_phone')
                ->label(__('ui.phone'))
                ->tel()
                ->visible(fn (Get $get): bool => in_array($get('channel'), ['whatsapp', 'sms'], true))
                ->required(fn (Get $get): bool => in_array($get('channel'), ['whatsapp', 'sms'], true)),

            Forms\Components\TextInput::make('recipient_email')
                ->label(__('ui.email'))
                ->email()
                ->visible(fn (Get $get): bool => $get('channel') === 'email')
                ->required(fn (Get $get): bool => $get('channel') === 'email'),

            Forms\Components\TextInput::make('subject')
                ->label(__('ui.message_subject'))
                ->visible(fn (Get $get): bool => $get('channel') === 'email')
                ->required(fn (Get $get): bool => $get('channel') === 'email'),

            Forms\Components\Textarea::make('message_body')
                ->label(__('ui.message'))
                ->rows(8)
                ->required()
                ->extraAttributes(fn (Get $get): array => [
                    'dir' => in_array($get('message_language'), ['he', 'ar'], true) ? 'rtl' : 'ltr',
                ]),
        ];
    }

    private static function handleSend(Tenant $tenant, array $data, $livewire): void
    {
        $paymentUrl = trim((string) ($data['payment_url'] ?? ''));
        if ($paymentUrl !== '' && $paymentUrl !== $tenant->renewal_payment_url) {
            $tenant->update(['renewal_payment_url' => $paymentUrl]);
        }

        $channel = $data['channel'] ?? 'whatsapp';
        $body = (string) ($data['message_body'] ?? '');
        $lang = (string) ($data['message_language'] ?? 'he');

        if ($channel === 'whatsapp') {
            $url = app(TripMessageService::class)->buildWhatsAppUrl((string) $data['recipient_phone'], $body);
            $livewire->js('window.open(' . json_encode($url) . ', "_blank")');

            Notification::make()
                ->success()
                ->title(__('ui.renewal_link_sent'))
                ->send();

            return;
        }

        if ($channel === 'sms') {
            $result = app(InforuSmsService::class)->sendSms((string) $data['recipient_phone'], str_replace('*', '', $body));
            if ($result['success']) {
                Notification::make()->success()->title(__('ui.renewal_link_sent'))->send();
            } else {
                Notification::make()->danger()->title(__('ui.sms_send_failed'))->send();
            }

            return;
        }

        Mail::to((string) $data['recipient_email'])->send(new TenantRenewalMail(
            tenant: $tenant,
            subjectLine: (string) ($data['subject'] ?? __('ui.send_renewal_link')),
            bodyText: $body,
            paymentUrl: $paymentUrl,
            localeCode: $lang,
        ));

        Notification::make()
            ->success()
            ->title(__('ui.renewal_link_sent'))
            ->send();
    }
}
