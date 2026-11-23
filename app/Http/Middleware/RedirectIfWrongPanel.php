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
                return redirect('/agent/login')->withErrors([
                    'email' => __('ui.suspended_message'),
                ]);
            }

            if ($user->tenant && $user->tenant->needsPaymentPaywall()) {
                if ($panel->getId() === 'admin') {
                    return redirect('/agent/billing-and-invoices');
                }

                $targetPath = $user->isAgencyOwner() ? 'agent/billing-and-invoices' : 'agent';
                $isAllowedPath = $request->is($targetPath)
                    || $request->is('agent/logout')
                    || $request->is('livewire/*');

                if (! $isAllowedPath) {
                    return redirect('/' . $targetPath);
                }
            }

            if ($panel->getId() === 'admin') {
                return redirect('/agent');
            }
        }

        if ($user->role === 'super_admin' && $panel->getId() === 'agent') {
            if (!$user->isImpersonatingTenant()) {
                return redirect('/admin');
            }

            if (!$user->getActiveTenant()) {
                $user->stopImpersonatingTenant();
                return redirect('/admin');
            }
        }

        return $next($request);
    }
}
