<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InforuSmsService
{
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '00972')) {
            $digits = '0' . substr($digits, 5);
        } elseif (str_starts_with($digits, '972') && strlen($digits) >= 11 && strlen($digits) <= 12) {
            $digits = '0' . substr($digits, 3);
        }

        return $digits;
    }

    /**
     * Send an SMS via InforUMobile v2 REST API.
     *
     * @return array{success: bool, error: ?string}
     */
    public function sendSms(string $phone, string $message): array
    {
        $endpoint = (string) config('services.inforu.endpoint', 'https://capi.inforu.co.il/api/v2/SMS/SendSms');
        $rawAuth = trim((string) config('services.inforu.basic_auth', ''));
        $sender = trim((string) config('services.inforu.sender', 'AsafDigital'));

        if ($rawAuth === '') {
            Log::error('InforuSmsService: Missing INFORU_BASIC_AUTH configuration.');

            return ['success' => false, 'error' => 'SMS gateway is not configured.'];
        }

        $authHeader = str_starts_with($rawAuth, 'Basic ') ? $rawAuth : 'Basic ' . $rawAuth;
        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            return ['success' => false, 'error' => 'Invalid phone number.'];
        }

        $payload = [
            'Data' => [
                'Message' => $message,
                'Recipients' => [
                    ['Phone' => $normalizedPhone],
                ],
            ],
        ];

        if ($sender !== '') {
            $payload['Data']['Settings'] = [
                'Sender' => substr($sender, 0, 11),
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json; charset=utf-8',
                ])
                ->post($endpoint, $payload);

            $json = $response->json();
            $statusId = is_array($json) ? ($json['StatusId'] ?? null) : null;

            if ($response->successful() && ($statusId === 1 || $statusId === null)) {
                return ['success' => true, 'error' => null];
            }

            $description = is_array($json) ? ($json['StatusDescription'] ?? $response->body()) : $response->body();
            Log::warning('InforuSmsService: Failed to send SMS.', [
                'status' => $response->status(),
                'status_id' => $statusId,
                'description' => $description,
                'phone' => $normalizedPhone,
            ]);

            return ['success' => false, 'error' => (string) $description];
        } catch (\Throwable $e) {
            Log::error('InforuSmsService: Exception while sending SMS.', [
                'message' => $e->getMessage(),
                'phone' => $normalizedPhone,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
