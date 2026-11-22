<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantImpersonationController extends Controller
{
    public function enter(Request $request, Tenant $tenant): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403);

        $user->startImpersonatingTenant($tenant);

        return redirect('/agent');
    }

    public function switchTenant(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $user->startImpersonatingTenant($tenant);

        return redirect('/agent');
    }

    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403);

        $user->stopImpersonatingTenant();

        return redirect('/');
    }
}
