<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request, WebPushService $webPushService): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
            'deviceLabel' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $endpointHash = hash('sha256', $validated['endpoint']);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => $endpointHash],
            [
                'user_id' => $user->id,
                'tenant_id' => $user->getActiveTenantId(),
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
                'device_label' => $validated['deviceLabel'] ?? substr((string) $request->userAgent(), 0, 120),
            ]
        );

        $welcomeSent = 0;
        if (! app()->runningUnitTests()) {
            try {
                $lang = $user->language ?: ($user->getActiveTenant()?->language ?: 'he');
                $welcomeSent = $webPushService->sendToUser(
                    user: $user,
                    title: __('ui.push_welcome_title', [], $lang),
                    body: __('ui.push_welcome_body', [], $lang),
                    url: $user->isSuperAdmin() ? '/admin' : '/agent',
                    tag: 'tourism-sales-welcome',
                    locale: $lang,
                );
            } catch (\Throwable) {
                // Keep subscription confirmation resilient
            }
        }

        return response()->json([
            'ok' => true,
            'id' => $subscription->id,
            'count' => $user->pushSubscriptions()->count(),
            'welcome_sent' => $welcomeSent > 0,
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $hash = hash('sha256', $validated['endpoint']);

        PushSubscription::where('user_id', $user->id)
            ->where('endpoint_hash', $hash)
            ->delete();

        return response()->json([
            'ok' => true,
            'count' => $user->pushSubscriptions()->count(),
        ]);
    }

    public function sendTest(Request $request, WebPushService $webPushService): JsonResponse
    {
        $user = $request->user();
        $lang = $user->language ?: ($user->getActiveTenant()?->language ?: 'he');

        $sent = $webPushService->sendToUser(
            user: $user,
            title: __('ui.push_test_title', [], $lang),
            body: __('ui.push_test_body', [], $lang),
            url: '/agent',
            tag: 'tourism-sales-test',
            locale: $lang,
        );

        return response()->json([
            'ok' => $sent > 0,
            'sent' => $sent,
        ]);
    }
}
