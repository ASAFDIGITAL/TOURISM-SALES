@php
    $user = auth()->user();
    $tenant = $user?->getActiveTenant();
@endphp

@if ($tenant && $tenant->isOnTrial())
    @php
        $daysLeft = $tenant->getTrialDaysLeft();
        $planName = $tenant->plan?->getLocalizedName() ?? '';
        $upgradeHref = '/agent/billing-and-invoices';
    @endphp
    <div style="margin-bottom: 14px; border-radius: 14px; padding: 12px 16px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.14) 0%, rgba(15, 23, 42, 0.06) 100%); border: 1px solid rgba(245, 158, 11, 0.45); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9px; background: #f59e0b; color: #0f172a; font-weight: 800; font-size: 0.85rem;">★</span>
            <div style="font-size: 0.88rem; font-weight: 600;">
                {{ __('ui.trial_banner_text', ['days' => $daysLeft, 'plan' => $planName]) }}
            </div>
        </div>
        <a
            href="{{ $upgradeHref }}"
            style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 10px; background: #0f172a; color: #fbbf24; font-size: 0.8rem; font-weight: 700; text-decoration: none;"
        >
            {{ __('ui.upgrade_now_btn') }}
        </a>
    </div>
@endif
