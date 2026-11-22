<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'type',
        'target_type',
        'tenant_id',
        'user_id',
        'starts_at',
        'ends_at',
        'is_active',
        'is_dismissible',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'is_dismissible' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeVisibleFor(Builder $query, User $user): Builder
    {
        $activeTenantId = $user->getActiveTenantId();

        return $query->currentlyActive()
            ->where(function (Builder $q) use ($user, $activeTenantId) {
                $q->where('target_type', 'all')
                    ->orWhere(function (Builder $q2) use ($user) {
                        $q2->where('target_type', 'user')
                            ->where('user_id', $user->id);
                    });

                if ($activeTenantId) {
                    $q->orWhere(function (Builder $q3) use ($activeTenantId) {
                        $q3->where('target_type', 'tenant')
                            ->where('tenant_id', $activeTenantId);
                    });
                }
            })
            ->latest('id');
    }

    public function getLiveStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'disabled';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'live';
    }
}
