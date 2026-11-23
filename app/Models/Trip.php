<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Traits\BelongsToTenant;

class Trip extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'customer_id',
        'title',
        'destination',
        'start_date',
        'end_date',
        'total_amount',
        'status',
        'notes',
        'hotels',
        'flights',
        'passengers',
        'trip_summary',
    ];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            if (! $trip->created_by_user_id && Auth::check()) {
                $trip->created_by_user_id = Auth::id();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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
