<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const IMPERSONATED_TENANT_SESSION_KEY = 'impersonated_tenant_id';

    public const AGENCY_ROLE_OWNER = 'owner';
    public const AGENCY_ROLE_STAFF = 'staff';

    public const PERM_VIEW_ALL_TRIPS = 'view_all_trips';
    public const PERM_VIEW_FINANCIAL_STATS = 'view_financial_stats';
    public const PERM_VIEW_SALES_REPORTS = 'view_sales_reports';
    public const PERM_VIEW_RECEIPTS = 'view_receipts';
    public const PERM_MANAGE_PAYMENTS = 'manage_payments';
    public const PERM_DELETE_RECORDS = 'delete_records';
    public const PERM_MANAGE_REMINDERS = 'manage_reminders';
    public const PERM_MANAGE_SETTINGS = 'manage_settings';

    protected ?Tenant $resolvedImpersonatedTenant = null;
    protected ?int $resolvedImpersonatedTenantId = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone',
        'password',
        'tenant_id',
        'role',
        'agency_role',
        'permissions',
        'language',
        'currency',
        'notify_collections',
        'notify_flights',
        'notify_reminders',
        'notify_expiring_tenants',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'notify_collections' => 'boolean',
            'notify_flights' => 'boolean',
            'notify_reminders' => 'boolean',
            'notify_expiring_tenants' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }

        if ($this->role === 'agent') {
            if ($this->tenant && $this->tenant->status !== 'active') {
                return false;
            }

            if ($panel->getId() === 'agent') {
                return $this->tenant_id !== null;
            }

            if ($panel->getId() === 'admin') {
                return true;
            }
        }

        return false;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAgencyOwner(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ($this->agency_role ?: self::AGENCY_ROLE_OWNER) === self::AGENCY_ROLE_OWNER;
    }

    public function isAgencyStaff(): bool
    {
        return ! $this->isSuperAdmin() && ($this->agency_role === self::AGENCY_ROLE_STAFF);
    }

    public function hasAgentPermission(string $permission): bool
    {
        if ($this->isAgencyOwner()) {
            return true;
        }

        return in_array($permission, (array) ($this->permissions ?? []), true);
    }

    /**
     * @return array<string, string>
     */
    public static function permissionOptions(): array
    {
        return [
            self::PERM_VIEW_ALL_TRIPS => __('ui.perm_view_all_trips'),
            self::PERM_VIEW_FINANCIAL_STATS => __('ui.perm_view_financial_stats'),
            self::PERM_VIEW_SALES_REPORTS => __('ui.perm_view_sales_reports'),
            self::PERM_VIEW_RECEIPTS => __('ui.perm_view_receipts'),
            self::PERM_MANAGE_PAYMENTS => __('ui.perm_manage_payments'),
            self::PERM_DELETE_RECORDS => __('ui.perm_delete_records'),
            self::PERM_MANAGE_REMINDERS => __('ui.perm_manage_reminders'),
            self::PERM_MANAGE_SETTINGS => __('ui.perm_manage_settings'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function rolePresetPermissions(string $preset): array
    {
        return match ($preset) {
            'sales_rep' => [
                self::PERM_MANAGE_PAYMENTS,
            ],
            'operations' => [
                self::PERM_VIEW_ALL_TRIPS,
                self::PERM_VIEW_RECEIPTS,
                self::PERM_MANAGE_PAYMENTS,
                self::PERM_MANAGE_REMINDERS,
            ],
            'office_manager' => [
                self::PERM_VIEW_ALL_TRIPS,
                self::PERM_VIEW_FINANCIAL_STATS,
                self::PERM_VIEW_SALES_REPORTS,
                self::PERM_VIEW_RECEIPTS,
                self::PERM_MANAGE_PAYMENTS,
                self::PERM_MANAGE_REMINDERS,
            ],
            default => [],
        };
    }

    public function isImpersonatingTenant(): bool
    {
        return $this->isSuperAdmin() && session()->has(self::IMPERSONATED_TENANT_SESSION_KEY);
    }

    public function getActiveTenantId(): ?int
    {
        if ($this->isSuperAdmin()) {
            $impersonatedId = session(self::IMPERSONATED_TENANT_SESSION_KEY);

            return $impersonatedId ? (int) $impersonatedId : null;
        }

        return $this->tenant_id ? (int) $this->tenant_id : null;
    }

    public function getActiveTenant(): ?Tenant
    {
        if ($this->isSuperAdmin()) {
            $tenantId = $this->getActiveTenantId();

            if (! $tenantId) {
                $this->resolvedImpersonatedTenant = null;
                $this->resolvedImpersonatedTenantId = null;

                return null;
            }

            if ($this->resolvedImpersonatedTenantId !== $tenantId) {
                $this->resolvedImpersonatedTenant = Tenant::find($tenantId);
                $this->resolvedImpersonatedTenantId = $tenantId;
            }

            return $this->resolvedImpersonatedTenant;
        }

        return $this->tenant;
    }

    public function startImpersonatingTenant(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        session()->put(self::IMPERSONATED_TENANT_SESSION_KEY, $tenantId);
        $this->resolvedImpersonatedTenant = $tenant instanceof Tenant ? $tenant : null;
        $this->resolvedImpersonatedTenantId = $tenant instanceof Tenant ? $tenantId : null;
    }

    public function stopImpersonatingTenant(): void
    {
        session()->forget(self::IMPERSONATED_TENANT_SESSION_KEY);
        $this->resolvedImpersonatedTenant = null;
        $this->resolvedImpersonatedTenantId = null;
    }
}
