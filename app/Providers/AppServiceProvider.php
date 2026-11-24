<?php

namespace App\Providers;

use App\Models\AgentActivityLog;
use App\Models\Customer;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Always format currency and numbers with Western Latin digits (0-9),
        // preventing Eastern Arabic-Indic digits (١٤٩٫٠٠) when locale is Arabic on Linux ICU.
        Number::useLocale('en');
        Table::$defaultNumberLocale = 'en';
        Infolist::$defaultNumberLocale = 'en';

        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;
            if (! $user instanceof User || $user->isSuperAdmin()) {
                return;
            }

            $request = request();
            $ip = AgentActivityLog::resolveClientIp($request);
            $ua = Str::limit((string) ($request?->userAgent() ?? ''), 255, '');
            $path = AgentActivityLog::normalizePath($request);

            $loginEvent = AgentActivityLog::EVENT_LOGIN;
            if ($path && str_contains($path, '/auth/google')) {
                $loginEvent = AgentActivityLog::EVENT_LOGIN_GOOGLE;
            } elseif ($request && ($request->has('otp_code') || str_contains((string) $path, 'otp'))) {
                $loginEvent = AgentActivityLog::EVENT_LOGIN_OTP;
            }

            try {
                $user->forceFill([
                    'last_login_at' => now(),
                    'last_seen_at' => now(),
                    'last_ip' => $ip,
                    'last_user_agent' => $ua !== '' ? $ua : $user->last_user_agent,
                    'last_page' => $path ?: '/agent',
                ])->saveQuietly();

                if ($user->tenant) {
                    $user->tenant->forceFill(['last_activity_at' => now()])->saveQuietly();
                }

                Cache::put('presence_user_' . $user->id, true, now()->addSeconds(60));
                Cache::put('activity_log_session_' . $user->id, true, now()->addMinutes(30));

                AgentActivityLog::recordForUser($user, $loginEvent, $request, $path ?: '/agent');
            } catch (\Throwable) {
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;
            if (! $user instanceof User || $user->isSuperAdmin()) {
                return;
            }

            try {
                AgentActivityLog::recordForUser($user, AgentActivityLog::EVENT_LOGOUT, request());

                $user->forceFill(['last_seen_at' => null])->saveQuietly();
                Cache::forget('presence_user_' . $user->id);
                Cache::forget('activity_log_session_' . $user->id);
            } catch (\Throwable) {
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            try {
                $matchedUser = $event->user instanceof User ? $event->user : null;
                $attempted = trim((string) ($event->credentials['email'] ?? $event->credentials['phone'] ?? ''));

                AgentActivityLog::recordAnonymous(
                    event: AgentActivityLog::EVENT_LOGIN_FAILED,
                    userId: $matchedUser?->id,
                    tenantId: $matchedUser?->tenant_id,
                    details: $attempted !== '' ? "Attempted: {$attempted}" : 'Invalid credentials',
                    request: request(),
                );
            } catch (\Throwable) {
            }
        });

        // Forensic deletion audit trail for critical agency CRM records
        $logDeletion = function (string $label, $record): void {
            $actor = auth()->user();
            if (! $actor instanceof User) {
                return;
            }

            $title = trim((string) ($record->name ?? $record->title ?? ('#' . $record->getKey())));
            AgentActivityLog::recordForUser(
                user: $actor,
                event: AgentActivityLog::EVENT_RECORD_DELETED,
                request: request(),
                details: "{$label} #{$record->getKey()} ({$title})",
                allowSuperAdmin: true,
                overrideTenantId: $record->tenant_id ?? $actor->getActiveTenantId(),
            );
        };

        Customer::deleted(fn (Customer $record) => $logDeletion('Customer', $record));
        Trip::deleted(fn (Trip $record) => $logDeletion('Trip', $record));
        Payment::deleted(fn (Payment $record) => $logDeletion('Payment', $record));
        Hotel::deleted(fn (Hotel $record) => $logDeletion('Hotel', $record));
    }
}
