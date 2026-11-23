<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackTenantActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (! $user->isSuperAdmin()) {
                $userKey = 'activity_user_' . $user->id;
                if (! Cache::has($userKey)) {
                    $user->forceFill(['last_login_at' => now()])->saveQuietly();
                    Cache::put($userKey, true, now()->addMinutes(15));
                }

                $tenant = $user->getActiveTenant();
                if ($tenant) {
                    $tenantKey = 'activity_tenant_' . $tenant->id;
                    if (! Cache::has($tenantKey)) {
                        $tenant->forceFill(['last_activity_at' => now()])->saveQuietly();
                        Cache::put($tenantKey, true, now()->addMinutes(15));
                    }
                }
            }
        }

        return $next($request);
    }
}
