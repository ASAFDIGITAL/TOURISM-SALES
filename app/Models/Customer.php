<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\BelongsToTenant;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'email',
        'phone',
        'passport_number',
        'passport_expires_at',
        'address',
        'notes',
    ];

    protected $casts = [
        'passport_expires_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (! $customer->created_by_user_id && Auth::check()) {
                $customer->created_by_user_id = Auth::id();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function hasPassportExpiryRisk(?\Carbon\Carbon $departureDate = null, int $monthsRequired = 6): bool
    {
        if (! $this->passport_expires_at) {
            return false;
        }

        $ref = ($departureDate ?? now())->copy()->startOfDay();

        return $this->passport_expires_at->copy()->startOfDay()->lt($ref->addMonths($monthsRequired));
    }
}
