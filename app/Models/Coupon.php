<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'plan_id',
        'billing_cycle',
        'upay_override_url',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $attributes = [
        'discount_type' => self::TYPE_PERCENT,
        'used_count' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon): void {
            $coupon->code = strtoupper(trim((string) $coupon->code));
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public static function findValidByCode(?string $rawCode, ?Plan $plan = null, ?string $cycle = null): ?self
    {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '') {
            return null;
        }

        $coupon = static::where('code', $code)->first();
        if (! $coupon) {
            return null;
        }

        return $coupon->isValidFor($plan, $cycle) ? $coupon : null;
    }

    public function isValidFor(?Plan $plan = null, ?string $cycle = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($plan !== null && $this->plan_id !== null && (int) $this->plan_id !== (int) $plan->id) {
            return false;
        }

        if ($cycle !== null && filled($this->billing_cycle) && $this->billing_cycle !== $cycle) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal): float
    {
        $subtotal = max(0.0, $subtotal);
        if ($subtotal <= 0.0) {
            return 0.0;
        }

        if ($this->discount_type === self::TYPE_FIXED) {
            return round(min($subtotal, max(0.0, (float) $this->discount_value)), 2);
        }

        $percent = min(100.0, max(0.0, (float) $this->discount_value));

        return round($subtotal * ($percent / 100), 2);
    }

    public function getFormattedValue(string $currencySymbol = '₪'): string
    {
        $val = (float) $this->discount_value;
        $formatted = fmod($val, 1.0) === 0.0 ? number_format($val, 0) : number_format($val, 2);

        if ($this->discount_type === self::TYPE_FIXED) {
            return $currencySymbol . $formatted;
        }

        return $formatted . '%';
    }
}
