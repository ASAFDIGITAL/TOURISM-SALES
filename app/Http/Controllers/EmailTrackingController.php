<?php

namespace App\Http\Controllers;

use App\Filament\Agent\Resources\TripResource;
use App\Models\Reminder;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

class EmailTrackingController extends Controller
{
    // 1x1 transparent GIF (43 bytes)
    private const TRANSPARENT_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $token): Response
    {
        $reminder = Reminder::withoutGlobalScopes()
            ->with(['trip.customer', 'tenant'])
            ->where('tracking_token', $token)
            ->first();

        if ($reminder) {
            $wasUnread = $reminder->opened_at === null;
            $now = now();

            $reminder->forceFill([
                'opened_at' => $reminder->opened_at ?? $now,
                'last_opened_at' => $now,
                'open_count' => (int) $reminder->open_count + 1,
            ])->save();

            if ($wasUnread) {
                $this->notifyAgentEmailOpened($reminder);
            }
        }

        return response(base64_decode(self::TRANSPARENT_GIF_BASE64), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function notifyAgentEmailOpened(Reminder $reminder): void
    {
        if (! $reminder->tenant_id) {
            return;
        }

        $trip = $reminder->trip;
        $customerName = $trip?->customer?->name ?: ($reminder->recipient_email ?: __('ui.customer'));
        $tripTitle = $trip?->title ?: '';

        $recipients = User::where('tenant_id', $reminder->tenant_id)
            ->where('role', 'agent')
            ->when(
                $trip?->created_by_user_id,
                fn ($q, $creatorId) => $q->where(fn ($inner) => $inner
                    ->where('id', $creatorId)
                    ->orWhere('agency_role', User::AGENCY_ROLE_OWNER))
            )
            ->get();

        $tripUrl = $trip
            ? TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent')
            : '/agent/reminders';

        foreach ($recipients as $agent) {
            $originalLocale = App::getLocale();
            $locale = $agent->language ?: ($reminder->tenant?->language ?: 'he');
            App::setLocale($locale);

            Notification::make()
                ->title(__('ui.email_opened_notif_title', ['customer' => $customerName]))
                ->body(__('ui.email_opened_notif_body', [
                    'customer' => $customerName,
                    'trip' => $tripTitle ?: ($reminder->recipient_email ?? ''),
                ]))
                ->icon('heroicon-o-envelope-open')
                ->success()
                ->actions([
                    Action::make('view')
                        ->label(__('ui.view_trip'))
                        ->url($tripUrl),
                ])
                ->sendToDatabase($agent);

            App::setLocale($originalLocale);
        }
    }
}
