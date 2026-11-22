@php
    $user = auth()->user();
    $isImpersonating = $user && $user->isImpersonatingTenant();
    $activeTenant = $isImpersonating ? $user->getActiveTenant() : null;
@endphp

@if ($isImpersonating && $activeTenant)
    @php
        $allTenants = \App\Models\Tenant::query()->orderBy('name')->get(['id', 'name', 'status']);
    @endphp
    <div
        style="
            background: linear-gradient(90deg, #D97706 0%, #F59E0B 100%);
            color: #ffffff;
            padding: 0.625rem 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.875rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            z-index: 25;
        "
    >
        <div style="display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap;">
            <span
                style="
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 1.5rem;
                    height: 1.5rem;
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, 0.2);
                    font-weight: 700;
                "
            >!</span>
            <span style="font-weight: 600;">
                {{ __('ui.impersonating_tenant', ['tenant' => $activeTenant->name]) }}
            </span>

            @if ($activeTenant->status !== 'active')
                <span
                    style="
                        background: #dc2626;
                        color: #ffffff;
                        padding: 0.125rem 0.5rem;
                        border-radius: 9999px;
                        font-size: 0.75rem;
                        font-weight: 600;
                    "
                >
                    {{ __("ui.{$activeTenant->status}") }}
                </span>
            @endif
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <form method="POST" action="{{ route('admin.impersonate.switch') }}" style="display: flex; align-items: center; gap: 0.375rem; margin: 0;">
                @csrf
                <label for="impersonate-tenant-select" style="font-size: 0.75rem; opacity: 0.9;">
                    {{ __('ui.switch_agent') }}:
                </label>
                <select
                    id="impersonate-tenant-select"
                    name="tenant_id"
                    onchange="this.form.submit()"
                    style="
                        background: rgba(255, 255, 255, 0.95);
                        color: #111827;
                        border: 1px solid rgba(255, 255, 255, 0.3);
                        border-radius: 0.375rem;
                        padding: 0.25rem 1.75rem 0.25rem 0.5rem;
                        font-size: 0.8125rem;
                        font-weight: 500;
                        cursor: pointer;
                    "
                >
                    @foreach ($allTenants as $tenantOption)
                        <option value="{{ $tenantOption->id }}" @selected($tenantOption->id === $activeTenant->id)>
                            {{ $tenantOption->name }}@if($tenantOption->status !== 'active') ({{ __("ui.{$tenantOption->status}") }})@endif
                        </option>
                    @endforeach
                </select>
            </form>

            <form method="POST" action="{{ route('admin.impersonate.leave') }}" style="margin: 0;">
                @csrf
                <button
                    type="submit"
                    style="
                        background: #111827;
                        color: #ffffff;
                        border: none;
                        border-radius: 0.375rem;
                        padding: 0.375rem 0.75rem;
                        font-size: 0.8125rem;
                        font-weight: 600;
                        cursor: pointer;
                    "
                >
                    {{ __('ui.return_to_admin') }}
                </button>
            </form>
        </div>
    </div>
@endif
