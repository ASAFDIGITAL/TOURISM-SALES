<?php

namespace App\Services;

use App\Models\ReminderRule;
use App\Models\ReminderRuleLog;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class ReminderRuleEngine
{
    public function __construct(
        private TripMessageService $tripMessageService,
        private InforuSmsService $smsService,
        private WebPushService $webPushService,
    ) {}

    public function processAllRules(): int
    {
        $rules = ReminderRule::withoutGlobalScopes()
            ->with('tenant')
            ->where('is_active', true)
            ->get();

        $triggeredCount = 0;

        foreach ($rules as $rule) {
            if ($rule->tenant && ($rule->tenant->status !== 'active' || !$rule->tenant->hasRemindersEnabled())) {
                continue;
            }

            $trips = $this->findMatchingTrips($rule);

            foreach ($trips as $trip) {
                $alreadySent = ReminderRuleLog::where('reminder_rule_id', $rule->id)
                    ->where('trip_id', $trip->id)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                $this->dispatchRuleForTrip($rule, $trip);

                ReminderRuleLog::create([
                    'reminder_rule_id' => $rule->id,
                    'trip_id' => $trip->id,
                    'sent_at' => now(),
                ]);

                $triggeredCount++;
            }
        }

        return $triggeredCount;
    }

    /**
     * @return Collection<int, Trip>
     */
    public function findMatchingTrips(ReminderRule $rule): Collection
    {
        $days = (int) $rule->days_offset;
        $baseQuery = Trip::withoutGlobalScopes()
            ->with(['customer', 'tenant', 'payments'])
            ->where('tenant_id', $rule->tenant_id)
            ->where('status', '!=', 'cancelled');

        return match ($rule->trigger_type) {
            ReminderRule::TRIGGER_DAYS_BEFORE_TRIP => $baseQuery
                ->whereDate('start_date', Carbon::today()->addDays($days)->format('Y-m-d'))
                ->get(),

            ReminderRule::TRIGGER_DAYS_AFTER_TRIP => $baseQuery
                ->whereDate('end_date', Carbon::today()->subDays($days)->format('Y-m-d'))
                ->get(),

            ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE => $baseQuery
                ->whereDate('start_date', Carbon::today()->addDays($days)->format('Y-m-d'))
                ->get()
                ->filter(fn (Trip $trip): bool => (float) $trip->balance > 0)
                ->values(),

            ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID => $baseQuery
                ->whereDate('created_at', Carbon::today()->subDays($days)->format('Y-m-d'))
                ->get()
                ->filter(fn (Trip $trip): bool => (float) $trip->balance > 0)
                ->values(),

            default => collect(),
        };
    }

    public function buildMessageForRule(ReminderRule $rule, Trip $trip): array
    {
        $lang = $rule->message_language ?: ($trip->tenant?->language ?: 'he');

        if ($rule->template_type === 'custom' && filled($rule->custom_message)) {
            $body = $this->replacePlaceholders($rule->custom_message, $trip);

            return [
                'subject' => $rule->name . ' - ' . $trip->title,
                'body' => $body,
                'lang' => $lang,
            ];
        }

        $built = $this->tripMessageService->buildMessage($trip, $rule->template_type, $lang);

        return [
            'subject' => $built['subject'],
            'body' => $built['body'],
            'lang' => $lang,
        ];
    }

    public function replacePlaceholders(string $template, Trip $trip): string
    {
        $currency = $trip->tenant?->currency ?? 'ILS';
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $replacements = [
            '{customer}' => $trip->customer?->name ?? '',
            '{trip}' => $trip->title ?? '',
            '{destination}' => $trip->destination ?? '',
            '{start_date}' => $trip->start_date?->format('d/m/Y') ?? '',
            '{end_date}' => $trip->end_date?->format('d/m/Y') ?? '',
            '{total}' => $symbol . number_format((float) $trip->total_amount, 2),
            '{paid}' => $symbol . number_format((float) $trip->paid_amount, 2),
            '{balance}' => $symbol . number_format(max(0, (float) $trip->balance), 2),
            '{agency}' => $trip->tenant?->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public function createDefaultRulesForTenant(int $tenantId, string $lang = 'he'): int
    {
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant && !$tenant->hasRemindersEnabled()) {
            return 0;
        }

        $allowed = $tenant ? $tenant->getAllowedChannels() : \App\Models\Tenant::ALL_CHANNELS;
        $autoChannels = array_values(array_intersect($allowed, ['push', 'email', 'sms']));

        if (count($autoChannels) === 0) {
            return 0;
        }

        $hasPush = in_array('push', $autoChannels, true);
        $hasCustomerChannel = in_array('email', $autoChannels, true) || in_array('sms', $autoChannels, true);

        $resolveChannel = function (string $preferred) use ($autoChannels): string {
            if ($preferred === 'all') {
                return count($autoChannels) > 1 ? 'all' : $autoChannels[0];
            }

            if (in_array($preferred, $autoChannels, true)) {
                return $preferred;
            }

            foreach (['email', 'sms', 'push'] as $fallback) {
                if (in_array($fallback, $autoChannels, true)) {
                    return $fallback;
                }
            }

            return $autoChannels[0];
        };

        $resolveRecipient = function (string $preferred, string $resolvedChannel) use ($hasPush, $hasCustomerChannel): string {
            if ($resolvedChannel === 'push' || !$hasCustomerChannel) {
                return 'agent';
            }

            if (!$hasPush) {
                return 'customer';
            }

            return $preferred;
        };

        $defaults = match ($lang) {
            'ar' => [
                [
                    'name' => 'تذكير تحصيل قبل 14 يوماً من السفر',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 14,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'تذكير دفع عاجل قبل 3 أيام',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 3,
                    'recipient_type' => 'both',
                    'channel' => 'sms',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'تذكير رحلة وطيران قبل يوم واحد',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_TRIP,
                    'days_offset' => 1,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PRE_TRIP_REMINDER,
                ],
                [
                    'name' => 'متابعة دفعة أولى بعد 3 أيام من الحجز',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID,
                    'days_offset' => 3,
                    'recipient_type' => 'agent',
                    'channel' => 'push',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
            ],
            'en' => [
                [
                    'name' => 'Payment Reminder — 14 Days Before Trip',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 14,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'Urgent Payment Reminder — 3 Days Before',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 3,
                    'recipient_type' => 'both',
                    'channel' => 'sms',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'Pre-Flight Reminder — 1 Day Before',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_TRIP,
                    'days_offset' => 1,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PRE_TRIP_REMINDER,
                ],
                [
                    'name' => 'Deposit Follow-up — 3 Days After Booking',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID,
                    'days_offset' => 3,
                    'recipient_type' => 'agent',
                    'channel' => 'push',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
            ],
            default => [
                [
                    'name' => 'תזכורת גבייה — 14 ימים לפני יציאה',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 14,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'תזכורת תשלום דחופה — 3 ימים לפני יציאה',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
                    'days_offset' => 3,
                    'recipient_type' => 'both',
                    'channel' => 'sms',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
                [
                    'name' => 'תזכורת טיסה ונסיעה — יום לפני יציאה',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_BEFORE_TRIP,
                    'days_offset' => 1,
                    'recipient_type' => 'both',
                    'channel' => 'all',
                    'template_type' => TripMessageService::TEMPLATE_PRE_TRIP_REMINDER,
                ],
                [
                    'name' => 'מעקב מקדמה — 3 ימים אחרי פתיחת טיול',
                    'trigger_type' => ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID,
                    'days_offset' => 3,
                    'recipient_type' => 'agent',
                    'channel' => 'push',
                    'template_type' => TripMessageService::TEMPLATE_PAYMENT_REMINDER,
                ],
            ],
        };

        $created = 0;
        foreach ($defaults as $data) {
            $channel = $resolveChannel($data['channel']);
            $recipient = $resolveRecipient($data['recipient_type'], $channel);

            ReminderRule::create([
                ...$data,
                'channel' => $channel,
                'recipient_type' => $recipient,
                'tenant_id' => $tenantId,
                'message_language' => $lang,
                'is_active' => true,
            ]);
            $created++;
        }

        return $created;
    }

    private function dispatchRuleForTrip(ReminderRule $rule, Trip $trip): void
    {
        $payload = $this->buildMessageForRule($rule, $trip);
        $tripUrl = \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent');
        $isPaymentRule = in_array($rule->trigger_type, [
            ReminderRule::TRIGGER_DAYS_BEFORE_PAYMENT_DUE,
            ReminderRule::TRIGGER_DAYS_AFTER_BOOKING_UNPAID,
        ], true);

        $tenant = $trip->tenant;

        // 1. Notify Agents (In-App + Phone Push)
        if (in_array($rule->recipient_type, ['agent', 'both'], true)) {
            $prefCol = $isPaymentRule ? 'notify_collections' : 'notify_flights';
            $agents = User::where('tenant_id', $trip->tenant_id)
                ->where($prefCol, true)
                ->get();

            $canPush = !$tenant || $tenant->canUseChannel('push');

            foreach ($agents as $agent) {
                $originalLocale = App::getLocale();
                $agentLocale = $agent->language ?: ($tenant?->language ?: 'he');
                App::setLocale($agentLocale);

                $title = $rule->name;
                $shortBody = ($trip->customer?->name ? $trip->customer->name . ' — ' : '') . $trip->title;

                Notification::make()
                    ->title($title)
                    ->icon($isPaymentRule ? 'heroicon-o-banknotes' : 'heroicon-o-bell-alert')
                    ->body($shortBody)
                    ->actions([
                        Action::make('view')
                            ->label(__('ui.view_trip'))
                            ->url($tripUrl, shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);

                if ($canPush && ($rule->usesChannel('push') || $rule->recipient_type === 'both')) {
                    $this->webPushService->sendToUser(
                        user: $agent,
                        title: $title,
                        body: $shortBody,
                        url: $tripUrl,
                        tag: "rule-{$rule->id}-trip-{$trip->id}",
                        locale: $agentLocale,
                    );
                }

                App::setLocale($originalLocale);
            }
        }

        // 2. Notify Customer (Email / SMS via InforU)
        if (in_array($rule->recipient_type, ['customer', 'both'], true) && $trip->customer) {
            $canEmail = !$tenant || $tenant->canUseChannel('email');
            $canSms = !$tenant || $tenant->canUseChannel('sms');

            if ($canEmail && $rule->usesChannel('email') && filled($trip->customer->email)) {
                $this->tripMessageService->sendEmail(
                    trip: $trip,
                    recipientEmail: $trip->customer->email,
                    subject: $payload['subject'],
                    body: $payload['body'],
                    locale: $payload['lang'],
                    attachItinerary: $rule->template_type === TripMessageService::TEMPLATE_TRIP_SUMMARY,
                );
            }

            if ($canSms && $rule->usesChannel('sms') && filled($trip->customer->phone)) {
                $smsText = str_replace('*', '', $payload['body']);
                $result = $this->smsService->sendSms($trip->customer->phone, $smsText);
                if ($result['success']) {
                    $this->tripMessageService->logSentMessage($trip, 'sms', $smsText);
                }
            }
        }
    }
}
