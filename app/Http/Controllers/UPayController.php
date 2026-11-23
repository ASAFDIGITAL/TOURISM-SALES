<?php

namespace App\Http\Controllers;

use App\Services\UPayService;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UPayController extends Controller
{
    /**
     * Server-to-Server IPN / Webhook endpoint ("קישור לעדכון שרת").
     */
    public function webhook(Request $request, UPayService $upay): JsonResponse
    {
        $payload = $request->all();
        if (empty($payload)) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $result = $upay->handleWebhookPayload($payload, (string) $request->query('key', ''));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * Browser Return URL after completing UPay payment ("קישור לדף החזרה").
     */
    public function returnPage(Request $request): RedirectResponse
    {
        $status = strtolower((string) $request->query('status', $request->query('result', 'success')));
        $isFailed = in_array($status, ['failed', 'error', 'cancel', 'cancelled', 'declined', '0'], true);

        if ($isFailed) {
            Notification::make()
                ->danger()
                ->title(__('ui.payplus_return_failure_title'))
                ->body(__('ui.payplus_return_failure_body'))
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title(__('ui.payplus_return_success_title'))
                ->body(__('ui.payplus_return_success_body'))
                ->send();
        }

        return redirect('/agent/billing-and-invoices');
    }
}
