<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderRule extends Model
{
    use HasFactory, BelongsToTenant;

    public const TRIGGER_DAYS_BEFORE_TRIP = 'days_before_trip';
    public const TRIGGER_DAYS_AFTER_TRIP = 'days_after_trip';
    public const TRIGGER_DAYS_BEFORE_PAYMENT_DUE = 'days_before_payment_due';
    public const TRIGGER_DAYS_AFTER_BOOKING_UNPAID = 'days_after_booking_unpaid';

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_type',
        'days_offset',
        'recipient_type',
        'channel',
        'template_type',
        'message_language',
        'custom_message',
        'is_active',
    ];

    protected $casts = [
        'days_offset' => 'integer',
        'is_active' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ReminderRuleLog::class);
    }

    /**
     * @return array<int, string>
     */
    public function getSelectedChannels(): array
    {
        $allowed = $this->tenant ? $this->tenant->getAllowedChannels() : ['push', 'email', 'sms'];
        $allowedAuto = array_values(array_intersect($allowed, ['push', 'email', 'sms']));

        $raw = trim((string) $this->channel);
        if ($raw === '' || $raw === 'all') {
            return $allowedAuto;
        }

        $parsed = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return array_values(array_intersect($parsed, $allowedAuto));
    }

    public function usesChannel(string $channel): bool
    {
        return in_array($channel, $this->getSelectedChannels(), true);
    }
}
