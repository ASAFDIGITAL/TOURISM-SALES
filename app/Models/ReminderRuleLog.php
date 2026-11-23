<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderRuleLog extends Model
{
    protected $fillable = [
        'reminder_rule_id',
        'trip_id',
        'sent_at',
        'channels_sent',
        'status',
        'note',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReminderRule::class, 'reminder_rule_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
