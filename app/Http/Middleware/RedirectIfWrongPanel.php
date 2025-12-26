<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfWrongPanel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $panel = Filament::getCurrentPanel();

        if (!$panel) {
            return $next($request);
        }

        if ($user->role === 'agent') {
            if ($user->tenant && $user->tenant->status !== 'active') {
                auth()->logout();
                return redirect('/login')->withErrors([
                    'email' => __('ui.suspended_message'),
                ]);
            }

            if ($panel->getId() === 'admin') {
                return redirect('/agent');
            }
        }

        if ($user->role === 'super_admin' && $panel->getId() === 'agent') {
            return redirect('/');
        }

        return $next($request);
    }
}
