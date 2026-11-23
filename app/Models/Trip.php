<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Traits\BelongsToTenant;

class Trip extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_QUOTE = 'quote';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'customer_id',
        'title',
        'destination',
        'start_date',
        'end_date',
        'total_amount',
        'supplier_name',
        'supplier_cost',
        'status',
        'public_token',
        'quote_approved_at',
        'quote_approved_by_name',
        'portal_views_count',
        'portal_last_viewed_at',
        'notes',
        'hotels',
        'flights',
        'passengers',
        'trip_summary',
    ];

    protected $attributes = [
        'supplier_cost' => 0,
        'portal_views_count' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            if (! $trip->created_by_user_id && Auth::check()) {
                $trip->created_by_user_id = Auth::id();
            }
            if (empty($trip->public_token)) {
                $trip->public_token = Str::random(32);
            }
        });
    }

    public function getOrCreatePublicToken(): string
    {
        if (empty($this->public_token)) {
            $this->forceFill(['public_token' => Str::random(32)])->save();
        }

        return (string) $this->public_token;
    }

    public function getPortalUrl(): string
    {
        return route('trip.portal.show', ['token' => $this->getOrCreatePublicToken()]);
    }

    public function isQuote(): bool
    {
        return $this->status === self::STATUS_QUOTE;
    }

    public function getNetProfitAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->supplier_cost, 2);
    }

    public function getProfitMarginAttribute(): float
    {
        $total = (float) $this->total_amount;
        if ($total <= 0) {
            return 0.0;
        }

        return round(($this->net_profit / $total) * 100, 1);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'supplier_cost' => 'decimal:2',
        'quote_approved_at' => 'datetime',
        'portal_last_viewed_at' => 'datetime',
        'portal_views_count' => 'integer',
        'hotels' => 'array',
        'flights' => 'array',
        'passengers' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function getPaidAmountAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }
}
