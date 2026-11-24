<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminAlertMailService
{
    /**
     * Send a branded alert email to a single Super Admin safely (never throws into caller flow).
     *
     * @param  array<string, string>  $details
     */
    public function sendToAdmin(
        User $admin,
        string $subject,
        string $body,
        string $badge = '',
        array $details = [],
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        string $tone = 'info',
    ): bool {
        $email = trim((string) $admin->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $absoluteUrl = null;
        if (filled($actionUrl)) {
            $absoluteUrl = str_starts_with($actionUrl, 'http://') || str_starts_with($actionUrl, 'https://')
                ? $actionUrl
                : url($actionUrl);
        }

        $lang = in_array($admin->language, ['he', 'ar', 'en'], true) ? $admin->language : 'he';

        try {
            Mail::to($email)->send(new AdminAlertMail(
                alertSubject: $subject,
                alertBody: $body,
                badgeText: $badge,
                details: array_filter($details, fn ($v) => $v !== null && trim((string) $v) !== ''),
                actionLabel: $actionLabel,
                actionUrl: $absoluteUrl,
                tone: $tone,
                lang: $lang,
            ));

            return true;
        } catch (\Throwable $e) {
            Log::warning('AdminAlertMailService failed to send admin alert email', [
                'admin_id' => $admin->id,
                'email' => $email,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
