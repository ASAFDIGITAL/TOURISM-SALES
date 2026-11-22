<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'phone',
        'password',
        'tenant_id',
        'role',
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
            // Check tenant status
            if ($this->tenant && $this->tenant->status !== 'active') {
                return false;
            }

            // Allow access to agent panel
            if ($panel->getId() === 'agent') {
                return $this->tenant_id !== null;
            }
            
            // Allow temporary access to admin panel (root) so they can be redirected to /agent
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

            if (!$tenantId) {
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
