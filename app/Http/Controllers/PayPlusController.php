<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PayPlusService;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayPlusController extends Controller
{
    public function checkout(Request $request, PayPlusService $payPlus): RedirectResponse
    {
        $user = $request->user();
        $tenant = $user?->getActiveTenant();

        abort_unless($user && $tenant && $user->isAgencyOwner(), 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'cycle' => ['nullable', 'in:monthly,yearly'],
            'extra_seats' => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $cycle = (string) ($validated['cycle'] ?? 'monthly');
        $extraSeats = (int) ($validated['extra_seats'] ?? $tenant->extra_seats ?? 0);

        $result = $payPlus->createRecurringPaymentLink($tenant, $plan, $cycle, $extraSeats);

        if (! ($result['ok'] ?? false) || empty($result['payment_url'])) {
            Notification::make()
                ->title(__('ui.payplus_checkout_unavailable_title'))
                ->body((string) ($result['error'] ?? __('ui.payplus_not_configured')))
                ->warning()
                ->send();

            return redirect('/agent/billing-and-invoices');
        }

        return redirect()->away($result['payment_url']);
    }

    public function webhook(Request $request, PayPlusService $payPlus): JsonResponse
    {
        $rawBody = $request->getContent();
        $hash = $request->header('hash');
        $userAgent = $request->header('user-agent');

        if (! $payPlus->verifyWebhookSignature($rawBody, $hash, $userAgent)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid JSON'], 400);
        }

        $invoice = $payPlus->handleWebhookPayload($payload);

        return response()->json([
            'ok' => $invoice !== null,
            'invoice_id' => $invoice?->id,
        ]);
    }

    public function success(): RedirectResponse
    {
        Notification::make()
            ->title(__('ui.payplus_return_success_title'))
            ->body(__('ui.payplus_return_success_body'))
            ->success()
            ->send();

        return redirect('/agent/billing-and-invoices');
    }

    public function failure(): RedirectResponse
    {
        Notification::make()
            ->title(__('ui.payplus_return_failure_title'))
            ->body(__('ui.payplus_return_failure_body'))
            ->danger()
            ->send();

        return redirect('/agent/billing-and-invoices');
    }
}
