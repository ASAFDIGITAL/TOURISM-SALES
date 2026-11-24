<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;
    public const RETENTION_MONTHS = 6;

    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGIN_GOOGLE = 'login_google';
    public const EVENT_LOGIN_OTP = 'login_otp';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_ACTIVE = 'active';
    public const EVENT_IP_CHANGED = 'ip_changed';
    public const EVENT_RECORD_DELETED = 'record_deleted';
    public const EVENT_IMPERSONATE_START = 'impersonate_start';
    public const EVENT_IMPERSONATE_END = 'impersonate_end';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'event',
        'ip_address',
        'device_label',
        'user_agent',
        'path',
        'details',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function resolveClientIp(?Request $request = null): ?string
    {
        $req = $request ?? request();
        if (! $req) {
            return null;
        }

        // Respect Cloudflare / reverse-proxy headers commonly used on Hostinger
        $cfIp = trim((string) $req->header('CF-Connecting-IP', ''));
        if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        $forwardedFor = trim((string) $req->header('X-Forwarded-For', ''));
        if ($forwardedFor !== '') {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return $req->ip();
    }

    public static function parseDeviceLabel(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return 'Unknown';
        }

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS X') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Device',
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome') && ! str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Firefox') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return "{$os} · {$browser}";
    }

    public static function normalizePath(?Request $request = null): ?string
    {
        $req = $request ?? request();
        if (! $req) {
            return null;
        }

        $path = '/' . ltrim($req->path(), '/');
        if (str_starts_with($path, '/livewire/')) {
            return null;
        }

        return Str::limit($path, 255, '');
    }

    public static function recordForUser(
        User $user,
        string $event,
        ?Request $request = null,
        ?string $overridePath = null,
        ?string $details = null,
        bool $allowSuperAdmin = false,
        ?int $overrideTenantId = null,
    ): ?self {
        if ($user->isSuperAdmin() && ! $allowSuperAdmin) {
            return null;
        }

        $req = $request ?? request();
        $ip = self::resolveClientIp($req);
        $ua = Str::limit((string) ($req?->userAgent() ?? ''), 255, '');
        $path = $overridePath ?? self::normalizePath($req) ?? $user->last_page;

        try {
            return static::create([
                'user_id' => $user->id,
                'tenant_id' => $overrideTenantId ?? $user->getActiveTenantId() ?? $user->tenant_id,
                'event' => $event,
                'ip_address' => $ip,
                'device_label' => self::parseDeviceLabel($ua),
                'user_agent' => $ua !== '' ? $ua : null,
                'path' => $path,
                'details' => $details !== null ? Str::limit($details, 255, '') : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function recordAnonymous(
        string $event,
        ?int $userId = null,
        ?int $tenantId = null,
        ?string $details = null,
        ?Request $request = null,
    ): ?self {
        $req = $request ?? request();
        $ip = self::resolveClientIp($req);
        $ua = Str::limit((string) ($req?->userAgent() ?? ''), 255, '');
        $path = self::normalizePath($req);

        try {
            return static::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'event' => $event,
                'ip_address' => $ip,
                'device_label' => self::parseDeviceLabel($ua),
                'user_agent' => $ua !== '' ? $ua : null,
                'path' => $path,
                'details' => $details !== null ? Str::limit($details, 255, '') : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
