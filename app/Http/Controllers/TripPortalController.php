<?php

namespace App\Http\Controllers;

use App\Filament\Agent\Resources\TripResource;
use App\Models\Attachment;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripDocumentationService;
use App\Services\WebPushService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripPortalController extends Controller
{
    public function show(string $token): View
    {
        $trip = $this->findTripByToken($token);
        $locale = $this->resolveLocale($trip);
        App::setLocale($locale);

        $trip->forceFill([
            'portal_views_count' => (int) $trip->portal_views_count + 1,
            'portal_last_viewed_at' => now(),
        ])->save();

        return view('portal.trip', [
            'trip' => $trip,
            'tenant' => $trip->tenant,
            'locale' => $locale,
        ]);
    }

    public function downloadItinerary(Request $request, string $token, TripDocumentationService $docs): Response
    {
        $trip = $this->findTripByToken($token);
        $requestedLang = (string) $request->query('lang', '');
        $lang = in_array($requestedLang, ['he', 'ar', 'en'], true)
            ? $requestedLang
            : $this->resolveLocale($trip);
        App::setLocale($lang);

        $pdf = $docs->getItineraryPdfContent($trip, $lang);
        $safeTitle = preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $trip->title ?: 'Trip');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeTitle . '_Itinerary_' . $lang . '.pdf"',
        ]);
    }

    public function downloadAttachment(string $token, int $attachment): StreamedResponse
    {
        $trip = $this->findTripByToken($token);

        $record = Attachment::withoutGlobalScopes()
            ->where('id', $attachment)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

        $disk = Storage::disk('public');
        abort_unless($record->path && $disk->exists($record->path), 404);

        return $disk->download($record->path, $record->name ?: basename($record->path));
    }

    public function approveQuote(Request $request, string $token, WebPushService $webPush): RedirectResponse
    {
        $trip = $this->findTripByToken($token);
        $locale = $this->resolveLocale($trip);
        App::setLocale($locale);

        $validated = $request->validate([
            'approver_name' => ['required', 'string', 'min:2', 'max:160'],
        ]);

        if ($trip->isQuote()) {
            $approverName = trim($validated['approver_name']);

            $trip->forceFill([
                'status' => Trip::STATUS_CONFIRMED,
                'quote_approved_at' => now(),
                'quote_approved_by_name' => $approverName,
            ])->save();

            $this->notifyAgentsQuoteApproved($trip, $approverName, $webPush);
        }

        return redirect()
            ->route('trip.portal.show', ['token' => $trip->public_token])
            ->with('quote_approved_success', true);
    }

    private function findTripByToken(string $token): Trip
    {
        return Trip::withoutGlobalScopes()
            ->with(['tenant', 'customer', 'payments', 'attachments'])
            ->where('public_token', $token)
            ->firstOrFail();
    }

    private function resolveLocale(Trip $trip): string
    {
        $lang = $trip->tenant?->language ?: 'he';

        return in_array($lang, ['he', 'ar', 'en'], true) ? $lang : 'he';
    }

    private function notifyAgentsQuoteApproved(Trip $trip, string $approverName, WebPushService $webPush): void
    {
        if (! $trip->tenant_id) {
            return;
        }

        $recipients = User::where('tenant_id', $trip->tenant_id)
            ->where('role', 'agent')
            ->when(
                $trip->created_by_user_id,
                fn ($q, $creatorId) => $q->where(fn ($inner) => $inner
                    ->where('id', $creatorId)
                    ->orWhere('agency_role', User::AGENCY_ROLE_OWNER))
            )
            ->get();

        $tripUrl = TripResource::getUrl('edit', ['record' => $trip->id], panel: 'agent');

        foreach ($recipients as $agent) {
            $origLocale = App::getLocale();
            $agentLocale = $agent->language ?: ($trip->tenant?->language ?: 'he');
            App::setLocale($agentLocale);

            $title = __('ui.quote_approved_notif_title', ['customer' => $approverName]);
            $body = __('ui.quote_approved_notif_body', [
                'customer' => $approverName,
                'trip' => $trip->title,
            ]);

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-check-badge')
                ->success()
                ->actions([
                    Action::make('view')
                        ->label(__('ui.view_trip'))
                        ->url($tripUrl),
                ])
                ->sendToDatabase($agent);

            try {
                $webPush->sendToUser($agent, $title, $body, url: $tripUrl, tag: 'quote-approved-' . $trip->id, locale: $agentLocale);
            } catch (\Throwable) {
            }

            App::setLocale($origLocale);
        }
    }
}
