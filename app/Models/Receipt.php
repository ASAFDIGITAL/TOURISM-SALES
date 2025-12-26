<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'trip_id',
        'payment_id', // keeping it for legacy for now, but will transition to payments relationship
        'receipt_number',
        'file_path',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Legacy relationship
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
