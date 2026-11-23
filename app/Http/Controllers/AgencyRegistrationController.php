<?php

namespace App\Http\Controllers;

use App\Filament\Resources\TenantResource;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReminderRuleEngine;
use App\Services\WebPushService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AgencyRegistrationController extends Controller
{
    public function show(Request $request): View
    {
        Plan::ensureDefaultPlansExist();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $selectedSlug = (string) $request->query('plan', 'pro');
        $selectedPlan = $plans->firstWhere('slug', $selectedSlug) ?? $plans->firstWhere('is_featured', true) ?? $plans->first();
        $billing = $request->query('billing') === 'yearly' ? 'yearly' : 'monthly';

        $locale = app()->getLocale();

        return view('auth.register', [
            'plans' => $plans,
            'selectedPlan' => $selectedPlan,
            'billing' => $billing,
            'locale' => $locale,
            'isRtl' => in_array($locale, ['he', 'ar'], true),
            'contactPhoneDisplay' => '050-231-1554',
            'contactPhoneIntl' => '972502311554',
        ]);
    }

    public function store(Request $request, WebPushService $webPush, ReminderRuleEngine $ruleEngine): RedirectResponse
    {
        Plan::ensureDefaultPlansExist();

        $validated = $request->validate([
            'agency_name' => ['required', 'string', 'min:2', 'max:160'],
            'owner_name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'plan_id' => ['required', 'exists:plans,id'],
            'currency' => ['nullable', 'in:ILS,USD,EUR,GBP'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $coupon = null;
        if (! empty(trim((string) ($validated['coupon_code'] ?? '')))) {
            $coupon = Coupon::findValidByCode($validated['coupon_code'], $plan);
            if (! $coupon) {
                return back()
                    ->withInput()
                    ->withErrors(['coupon_code' => __('ui.coupon_invalid_or_expired')]);
            }
        }
        $locale = in_array(app()->getLocale(), ['he', 'ar', 'en'], true) ? app()->getLocale() : 'he';
        $trialDays = max(1, (int) $plan->trial_days);
        $trialEnds = now()->addDays($trialDays);

        [$tenant, $user] = DB::transaction(function () use ($validated, $plan, $coupon, $locale, $trialEnds, $ruleEngine) {
            $tenant = Tenant::create([
                'name' => trim($validated['agency_name']),
                'email' => strtolower(trim($validated['email'])),
                'phone' => trim($validated['phone']),
                'status' => 'active',
                'subscription_type' => 'recurring',
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'extra_seats' => 0,
                'is_trial' => true,
                'trial_ends_at' => $trialEnds,
                'pdf_header_theme' => 'dark',
                'receipt_prefix' => 'REC',
                'receipt_next_number' => 1001,
                'currency' => $validated['currency'] ?? $plan->currency ?? 'ILS',
                'language' => $locale,
                'joined_at' => now()->toDateString(),
                'expires_at' => $trialEnds->toDateString(),
                'subscription_amount' => 0,
                'reminders_enabled' => (bool) $plan->reminders_enabled,
                'allowed_channels' => $plan->getAllowedChannels(),
            ]);

            $user = User::create([
                'name' => trim($validated['owner_name']),
                'email' => strtolower(trim($validated['email'])),
                'phone' => trim($validated['phone']),
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'agent',
                'agency_role' => User::AGENCY_ROLE_OWNER,
                'language' => $locale,
                'currency' => $tenant->currency,
            ]);

            if ($tenant->hasRemindersEnabled()) {
                $ruleEngine->createDefaultRulesForTenant($tenant->id);
            }

            return [$tenant, $user];
        });

        $this->notifyAdmins($tenant, $user, $plan, $webPush);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/agent');
    }

    protected function notifyAdmins(Tenant $tenant, User $owner, Plan $plan, WebPushService $webPush): void
    {
        $admins = User::query()->where('role', 'super_admin')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $title = __('ui.admin_new_signup_title', ['tenant' => $tenant->name]);
        $body = __('ui.admin_new_signup_body', [
            'owner' => $owner->name,
            'phone' => $owner->phone ?: '-',
            'plan' => $plan->getLocalizedName(),
            'days' => $plan->trial_days,
        ]);

        $editUrl = null;
        try {
            $editUrl = TenantResource::getUrl('edit', ['record' => $tenant], panel: 'admin');
        } catch (\Throwable) {
            $editUrl = '/admin/tenants/' . $tenant->id . '/edit';
        }

        foreach ($admins as $admin) {
            $notif = Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-building-storefront')
                ->success();

            if ($editUrl) {
                $notif->actions([
                    NotificationAction::make('view_tenant')
                        ->label(__('ui.manage_agent'))
                        ->url($editUrl)
                        ->button(),
                ]);
            }

            $notif->sendToDatabase($admin);

            try {
                $webPush->sendToUser(
                    $admin,
                    $title,
                    $body,
                    url: $editUrl,
                    tag: 'new-signup-' . $tenant->id,
                );
            } catch (\Throwable) {
            }
        }
    }
}
