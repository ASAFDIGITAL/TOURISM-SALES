<?php

namespace App\Filament\Pages\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\InforuSmsService;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.pages.auth.split-layout';

    protected static string $view = 'filament.pages.auth.login';

    public string $loginMethod = 'email';

    public string $phone = '';

    public string $otpCode = '';

    public bool $otpSent = false;

    public string $maskedPhone = '';

    public function switchLoginMethod(string $method): void
    {
        if (!in_array($method, ['email', 'phone'], true)) {
            return;
        }

        $this->loginMethod = $method;
        $this->resetValidation();
    }

    public function resetPhoneLogin(): void
    {
        $this->otpSent = false;
        $this->otpCode = '';
        $this->maskedPhone = '';
        $this->resetValidation();
    }

    public function sendOtp(): void
    {
        $this->resetValidation();

        $smsService = app(InforuSmsService::class);
        $normalizedPhone = $smsService->normalizePhone($this->phone);

        if (strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15) {
            throw ValidationException::withMessages([
                'phone' => __('ui.phone_not_found'),
            ]);
        }

        $rateLimitKey = 'login-otp-send:' . $normalizedPhone . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'phone' => __('ui.otp_rate_limited', ['seconds' => $seconds]),
            ]);
        }

        $cooldownKey = 'login-otp-cooldown:' . $normalizedPhone;
        if (Cache::has($cooldownKey)) {
            $seconds = max(1, (int) Cache::get($cooldownKey) - time());
            throw ValidationException::withMessages([
                'phone' => __('ui.otp_rate_limited', ['seconds' => $seconds]),
            ]);
        }

        $user = $this->findUserByPhone($normalizedPhone, $smsService);

        if (!$user) {
            RateLimiter::hit($rateLimitKey, 600);
            throw ValidationException::withMessages([
                'phone' => __('ui.phone_not_found'),
            ]);
        }

        if ($user->role === 'agent' && $user->tenant && $user->tenant->status !== 'active') {
            throw ValidationException::withMessages([
                'phone' => __('ui.suspended_message'),
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $message = __('ui.otp_sms_text', ['code' => $code]);

        $result = $smsService->sendSms($normalizedPhone, $message);

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'phone' => __('ui.sms_send_failed'),
            ]);
        }

        RateLimiter::hit($rateLimitKey, 600);
        Cache::put($cooldownKey, time() + 60, now()->addSeconds(60));
        Cache::put($this->otpCacheKey($normalizedPhone), [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(5));

        $this->otpSent = true;
        $this->otpCode = '';
        $this->maskedPhone = $this->maskPhone($normalizedPhone);
    }

    public function verifyOtp(): ?LoginResponse
    {
        $this->resetValidation();

        $smsService = app(InforuSmsService::class);
        $normalizedPhone = $smsService->normalizePhone($this->phone);
        $cacheKey = $this->otpCacheKey($normalizedPhone);
        $stored = Cache::get($cacheKey);

        if (!is_array($stored) || empty($stored['code_hash']) || empty($stored['user_id'])) {
            throw ValidationException::withMessages([
                'otpCode' => __('ui.otp_expired'),
            ]);
        }

        $submittedCode = preg_replace('/\D+/', '', $this->otpCode) ?? '';

        if ($submittedCode === '' || !Hash::check($submittedCode, $stored['code_hash'])) {
            $attempts = ((int) ($stored['attempts'] ?? 0)) + 1;
            if ($attempts >= 5) {
                Cache::forget($cacheKey);
                $this->otpSent = false;
                throw ValidationException::withMessages([
                    'otpCode' => __('ui.otp_expired'),
                ]);
            }

            $stored['attempts'] = $attempts;
            Cache::put($cacheKey, $stored, now()->addMinutes(5));

            throw ValidationException::withMessages([
                'otpCode' => __('ui.otp_invalid'),
            ]);
        }

        /** @var User|null $user */
        $user = User::with('tenant')->find($stored['user_id']);
        Cache::forget($cacheKey);

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => __('ui.phone_not_found'),
            ]);
        }

        if ($user->role === 'agent' && $user->tenant && $user->tenant->status !== 'active') {
            throw ValidationException::withMessages([
                'otpCode' => __('ui.suspended_message'),
            ]);
        }

        if (
            ($user instanceof FilamentUser) &&
            (!$user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            throw ValidationException::withMessages([
                'otpCode' => __('filament-panels::pages/auth/login.messages.failed'),
            ]);
        }

        Filament::auth()->login($user, remember: true);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $e) {
            $data = $this->form->getState();
            $user = User::where('email', $data['email'])->first();

            if ($user && $user->role === 'agent' && $user->tenant && $user->tenant->status !== 'active') {
                throw ValidationException::withMessages([
                    'data.email' => __('ui.suspended_message'),
                ]);
            }

            throw $e;
        }
    }

    /**
     * @return class-string<LoginResponse>
     */
    protected function getRedirectUrl(): string
    {
        $user = auth()->user();

        if ($user->role === 'super_admin') {
            return '/';
        }

        if ($user->role === 'agent') {
            return '/agent';
        }

        return parent::getRedirectUrl();
    }

    private function findUserByPhone(string $normalizedPhone, InforuSmsService $smsService): ?User
    {
        $directUser = User::with('tenant')
            ->whereNotNull('phone')
            ->get()
            ->first(fn (User $user): bool => $smsService->normalizePhone($user->phone) === $normalizedPhone);

        if ($directUser) {
            return $directUser;
        }

        // Fallback: match tenant phone if tenant has a single agent user without personal phone
        $matchingTenant = Tenant::with('users')
            ->whereNotNull('phone')
            ->get()
            ->first(fn (Tenant $tenant): bool => $smsService->normalizePhone($tenant->phone) === $normalizedPhone);

        if ($matchingTenant) {
            $agentUsers = $matchingTenant->users->where('role', 'agent');
            if ($agentUsers->count() === 1) {
                return $agentUsers->first();
            }
        }

        return null;
    }

    private function otpCacheKey(string $normalizedPhone): string
    {
        return 'login-otp-code:' . $normalizedPhone;
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 6) {
            return $phone;
        }

        return substr($phone, 0, 3) . str_repeat('*', $len - 6) . substr($phone, -3);
    }
}
