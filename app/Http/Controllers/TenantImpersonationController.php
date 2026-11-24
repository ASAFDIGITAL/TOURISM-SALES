<?php

namespace App\Http\Controllers;

use App\Models\AgentActivityLog;
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

        AgentActivityLog::recordForUser(
            user: $user,
            event: AgentActivityLog::EVENT_IMPERSONATE_START,
            request: $request,
            overridePath: '/agent',
            details: "Admin support entry: {$tenant->name}",
            allowSuperAdmin: true,
            overrideTenantId: $tenant->id,
        );

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

        AgentActivityLog::recordForUser(
            user: $user,
            event: AgentActivityLog::EVENT_IMPERSONATE_START,
            request: $request,
            overridePath: '/agent',
            details: "Admin support switch: {$tenant->name}",
            allowSuperAdmin: true,
            overrideTenantId: $tenant->id,
        );

        return redirect('/agent');
    }

    public function leave(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403);

        $previousTenantId = $user->getActiveTenantId();
        $user->stopImpersonatingTenant();

        if ($previousTenantId) {
            AgentActivityLog::recordForUser(
                user: $user,
                event: AgentActivityLog::EVENT_IMPERSONATE_END,
                request: $request,
                overridePath: '/admin',
                details: 'Admin exited agency support mode',
                allowSuperAdmin: true,
                overrideTenantId: $previousTenantId,
            );
        }

        return redirect('/admin');
    }
}
