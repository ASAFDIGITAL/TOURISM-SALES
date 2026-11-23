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
        'category',
        'recipient_type',
        'channel',
        'recipient_email',
        'tracking_token',
        'message_language',
        'scheduled_at',
        'sent_at',
        'opened_at',
        'last_opened_at',
        'open_count',
        'status',
        'message',
        'error_message',
    ];

    protected $attributes = [
        'open_count' => 0,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'open_count' => 'integer',
    ];

    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
