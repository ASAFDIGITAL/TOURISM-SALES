<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Reminder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'trip_id',
        'channel',
        'scheduled_at',
        'status',
        'message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
