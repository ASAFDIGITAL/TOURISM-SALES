<?php

namespace App\Http\Middleware;

use App\Models\AgentActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackTenantActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (! $user->isSuperAdmin()) {
                $ip = AgentActivityLog::resolveClientIp($request);
                $ua = Str::limit((string) $request->userAgent(), 255, '');
                $path = AgentActivityLog::normalizePath($request);

                $ipChanged = filled($ip) && filled($user->last_ip) && $user->last_ip !== $ip;
                $presenceKey = 'presence_user_' . $user->id;

                if ($ipChanged || ! Cache::has($presenceKey)) {
                    $updates = [
                        'last_seen_at' => now(),
                        'last_ip' => $ip,
                    ];

                    if ($ua !== '') {
                        $updates['last_user_agent'] = $ua;
                    }
                    if ($path !== null) {
                        $updates['last_page'] = $path;
                    }
                    if (! $user->last_login_at) {
                        $updates['last_login_at'] = now();
                    }

                    $user->forceFill($updates)->saveQuietly();
                    Cache::put($presenceKey, true, now()->addSeconds(60));
                }

                // Log IP change immediately, or a periodic session heartbeat every 30 min
                if ($ipChanged) {
                    AgentActivityLog::recordForUser($user, AgentActivityLog::EVENT_IP_CHANGED, $request, $path);
                } else {
                    $sessionLogKey = 'activity_log_session_' . $user->id;
                    if (! Cache::has($sessionLogKey)) {
                        AgentActivityLog::recordForUser($user, AgentActivityLog::EVENT_ACTIVE, $request, $path);
                        Cache::put($sessionLogKey, true, now()->addMinutes(30));
                    }
                }

                $tenant = $user->getActiveTenant();
                if ($tenant) {
                    $tenantKey = 'activity_tenant_' . $tenant->id;
                    if (! Cache::has($tenantKey)) {
                        $tenant->forceFill(['last_activity_at' => now()])->saveQuietly();
                        Cache::put($tenantKey, true, now()->addMinutes(5));
                    }
                }
            }
        }

        return $next($request);
    }
}
