<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    public const ALL_CHANNELS = ['push', 'email', 'whatsapp', 'sms'];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'subscription_type',
        'logo_path',
        'pdf_header_theme',
        'receipt_prefix',
        'receipt_next_number',
        'currency',
        'language',
        'joined_at',
        'expires_at',
        'subscription_amount',
        'renewal_payment_url',
        'reminders_enabled',
        'allowed_channels',
    ];

    protected $casts = [
        'reminders_enabled' => 'boolean',
        'allowed_channels' => 'array',
    ];

    public function isLifetime(): bool
    {
        return $this->subscription_type === 'lifetime';
    }

    public function hasRemindersEnabled(): bool
    {
        return $this->reminders_enabled ?? true;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedChannels(): array
    {
        if ($this->allowed_channels === null) {
            return self::ALL_CHANNELS;
        }

        return array_values(array_intersect(self::ALL_CHANNELS, (array) $this->allowed_channels));
    }

    public function canUseChannel(string $channel): bool
    {
        if (!$this->hasRemindersEnabled()) {
            return false;
        }

        return in_array($channel, $this->getAllowedChannels(), true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
    
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
