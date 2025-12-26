<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Trip extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
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
