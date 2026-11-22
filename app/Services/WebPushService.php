<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        ?string $url = null,
        ?string $tag = null,
        ?string $locale = null,
    ): int {
        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $publicKey = (string) config('services.webpush.public_key', '');
        $privateKey = (string) config('services.webpush.private_key', '');
        $subject = (string) config('services.webpush.subject', 'mailto:info@asafdigital.com');

        if ($publicKey === '' || $privateKey === '') {
            Log::warning('WebPushService: VAPID keys are missing.');

            return 0;
        }

        $lang = $locale ?: ($user->language ?: ($user->tenant?->language ?: 'he'));
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: '/agent',
            'icon' => '/images/pwa-icon-192.png',
            'badge' => '/images/favicon.png',
            'tag' => $tag ?: 'tourism-sales-alert',
            'lang' => $lang,
            'dir' => in_array($lang, ['he', 'ar'], true) ? 'rtl' : 'ltr',
        ], JSON_UNESCAPED_UNICODE);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]),
                $payload
            );
        }

        $sentCount = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sentCount++;
            } elseif ($report->isSubscriptionExpired()) {
                $hash = hash('sha256', $report->getEndpoint());
                PushSubscription::where('endpoint_hash', $hash)->delete();
            } else {
                Log::warning('WebPushService: Push delivery failed.', [
                    'endpoint' => $report->getEndpoint(),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $sentCount;
    }
}
