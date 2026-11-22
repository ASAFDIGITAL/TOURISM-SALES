<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\User;
use App\Services\InforuSmsService;
use App\Services\TripMessageService;
use App\Services\WebPushService;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class CheckReminders extends Command
{
    protected $signature = 'app:check-reminders';

    protected $description = 'Check for scheduled reminders and notify agents and/or customers';

    public function handle(
        WebPushService $webPushService,
        TripMessageService $tripMessageService,
        InforuSmsService $smsService,
    ): int {
        $now = Carbon::now();

        $reminders = Reminder::withoutGlobalScopes()
            ->with(['trip.customer', 'trip.tenant', 'tenant'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        $this->info("Found {$reminders->count()} pending reminders to process.");

        foreach ($reminders as $reminder) {
            $tenant = $reminder->tenant;
            if ($tenant && !$tenant->hasRemindersEnabled()) {
                continue;
            }

            $recipientType = $reminder->recipient_type ?: 'agent';
            $trip = $reminder->trip;
            $tripUrl = $trip
                ? \App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $reminder->trip_id], panel: 'agent')
                : '/agent';

            // 1. Deliver to Agents (In-App + Phone Push)
            if (in_array($recipientType, ['agent', 'both'], true) || $reminder->channel === 'whatsapp') {
                $agents = User::where('tenant_id', $reminder->tenant_id)
                    ->where('notify_reminders', true)
                    ->get();
                $canPush = !$tenant || $tenant->canUseChannel('push');

                foreach ($agents as $agent) {
                    $originalLocale = App::getLocale();
                    $locale = $agent->language ?: ($agent->tenant?->language ?: 'he');
                    App::setLocale($locale);

                    $destination = $trip ? ($trip->destination ?: $trip->title) : ($agent->tenant?->name ?? '');
                    $title = __('ui.trip_reminder', ['destination' => $destination]);
                    $body = __('ui.reminder_message', ['message' => $reminder->message]);

                    Notification::make()
                        ->title($title)
                        ->icon($reminder->category === 'payment' ? 'heroicon-o-banknotes' : 'heroicon-o-bell')
                        ->body($body)
                        ->actions([
                            Action::make('view')
                                ->label(__('ui.view_trip'))
                                ->url($tripUrl, shouldOpenInNewTab: true),
                        ])
                        ->sendToDatabase($agent);

                    if ($canPush) {
                        $webPushService->sendToUser(
                            user: $agent,
                            title: $title,
                            body: $body,
                            url: $tripUrl,
                            tag: "reminder-{$reminder->id}",
                            locale: $locale,
                        );
                    }

                    App::setLocale($originalLocale);
                }
            }

            // 2. Deliver to Customer (Email or SMS)
            if (in_array($recipientType, ['customer', 'both'], true) && $trip && $trip->customer) {
                $lang = $reminder->message_language ?: ($trip->tenant?->language ?: 'he');

                if ($reminder->channel === 'email' && (!$tenant || $tenant->canUseChannel('email')) && filled($trip->customer->email)) {
                    $tripMessageService->sendEmail(
                        trip: $trip,
                        recipientEmail: $trip->customer->email,
                        subject: $trip->title,
                        body: $reminder->message,
                        locale: $lang,
                        attachItinerary: false,
                    );
                } elseif ($reminder->channel === 'sms' && (!$tenant || $tenant->canUseChannel('sms')) && filled($trip->customer->phone)) {
                    $smsService->sendSms($trip->customer->phone, str_replace('*', '', $reminder->message));
                }
            }

            $reminder->update(['status' => 'sent']);
            $this->info("Processed reminder ID: {$reminder->id}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
