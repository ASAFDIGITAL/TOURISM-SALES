<?php

namespace App\Http\Controllers;

use App\Filament\Resources\TenantResource;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google.client_id', '');
        $clientSecret = (string) config('services.google.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            return back()->withErrors([
                'email' => __('ui.google_oauth_not_configured'),
            ]);
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_plan', (string) $request->query('plan', 'pro'));

        $redirectUri = $this->getRedirectUri();

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function callback(Request $request, WebPushService $webPush, ReminderRuleEngine $ruleEngine): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('google_oauth_state', '');
        $receivedState = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $receivedState) || $code === '') {
            return redirect('/agent/login')->withErrors([
                'email' => __('ui.google_auth_failed'),
            ]);
        }

        $tokenResponse = Http::asForm()
            ->timeout(12)
            ->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $this->getRedirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $tokenResponse->successful() || blank($tokenResponse->json('access_token'))) {
            return redirect('/agent/login')->withErrors([
                'email' => __('ui.google_auth_failed'),
            ]);
        }

        $profileResponse = Http::withToken((string) $tokenResponse->json('access_token'))
            ->timeout(12)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (! $profileResponse->successful()) {
            return redirect('/agent/login')->withErrors([
                'email' => __('ui.google_auth_failed'),
            ]);
        }

        $googleId = (string) $profileResponse->json('sub', '');
        $email = strtolower(trim((string) $profileResponse->json('email', '')));
        $name = trim((string) ($profileResponse->json('name') ?: explode('@', $email)[0]));

        if ($googleId === '' || $email === '') {
            return redirect('/agent/login')->withErrors([
                'email' => __('ui.google_auth_failed'),
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->update(['google_id' => $googleId]);
            }

            if ($user->role === 'agent' && $user->tenant) {
                if ($user->tenant->status !== 'active') {
                    return redirect('/agent/login')->withErrors([
                        'email' => __('ui.suspended_message'),
                    ]);
                }
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            if ($user->role === 'agent' && $user->tenant?->needsPaymentPaywall()) {
                return redirect($user->isAgencyOwner() ? '/agent/billing-and-invoices' : '/agent');
            }

            return redirect($user->isSuperAdmin() ? '/admin' : '/agent');
        }

        // New user via Google -> create a 14-day free trial agency automatically!
        Plan::ensureDefaultPlansExist();
        $planSlug = (string) $request->session()->pull('google_oauth_plan', 'pro');
        $plan = Plan::where('slug', $planSlug)->first()
            ?? Plan::where('is_featured', true)->first()
            ?? Plan::orderBy('sort_order')->first();

        $locale = in_array(app()->getLocale(), ['he', 'ar', 'en'], true) ? app()->getLocale() : 'he';
        $trialDays = max(1, (int) ($plan?->trial_days ?? 14));
        $trialEnds = now()->addDays($trialDays);

        [$tenant, $user] = DB::transaction(function () use ($name, $email, $googleId, $plan, $locale, $trialEnds, $ruleEngine) {
            $tenant = Tenant::create([
                'name' => $name,
                'email' => $email,
                'status' => 'active',
                'subscription_type' => 'recurring',
                'plan_id' => $plan?->id,
                'extra_seats' => 0,
                'is_trial' => true,
                'trial_ends_at' => $trialEnds,
                'pdf_header_theme' => 'dark',
                'receipt_prefix' => 'REC',
                'receipt_next_number' => 1001,
                'currency' => $plan?->currency ?? 'ILS',
                'language' => $locale,
                'joined_at' => now()->toDateString(),
                'expires_at' => $trialEnds->toDateString(),
                'subscription_amount' => 0,
                'reminders_enabled' => (bool) ($plan?->reminders_enabled ?? true),
                'allowed_channels' => $plan ? $plan->getAllowedChannels() : ['push', 'email', 'whatsapp'],
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
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

        $this->notifyAdminsNewGoogleSignup($tenant, $user, $plan, $webPush);

        try {
            app(\App\Services\TrialOnboardingService::class)->sendStepIfNeeded(
                $tenant,
                \App\Services\TrialOnboardingService::STEP_DAY_0
            );
        } catch (\Throwable) {
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/agent');
    }

    protected function getRedirectUri(): string
    {
        return (string) (config('services.google.redirect') ?: route('auth.google.callback'));
    }

    protected function notifyAdminsNewGoogleSignup(Tenant $tenant, User $owner, ?Plan $plan, WebPushService $webPush): void
    {
        $title = __('ui.admin_new_signup_title', ['tenant' => $tenant->name]);
        $body = __('ui.admin_new_signup_body', [
            'owner' => $owner->name . ' (Google)',
            'phone' => $owner->email,
            'plan' => $plan?->getLocalizedName() ?? 'Pro',
            'days' => $plan?->trial_days ?? 14,
        ]);

        $editUrl = null;
        try {
            $editUrl = TenantResource::getUrl('edit', ['record' => $tenant], panel: 'admin');
        } catch (\Throwable) {
            $editUrl = '/admin/tenants/' . $tenant->id . '/edit';
        }

        foreach (User::where('role', 'super_admin')->get() as $admin) {
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
                $webPush->sendToUser($admin, $title, $body, url: $editUrl, tag: 'new-signup-' . $tenant->id);
            } catch (\Throwable) {
            }
        }
    }
}
