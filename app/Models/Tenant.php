<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'plan_id',
        'coupon_id',
        'extra_seats',
        'extra_email_quota',
        'extra_sms_quota',
        'is_trial',
        'trial_ends_at',
        'trial_drip_steps_sent',
        'last_activity_at',
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
        'payplus_customer_uid',
        'payplus_recurring_uid',
        'payplus_billing_cycle',
        'payplus_status',
        'payplus_card_last4',
        'reminders_enabled',
        'allowed_channels',
    ];

    protected $casts = [
        'extra_seats' => 'integer',
        'extra_email_quota' => 'integer',
        'extra_sms_quota' => 'integer',
        'is_trial' => 'boolean',
        'trial_ends_at' => 'datetime',
        'trial_drip_steps_sent' => 'array',
        'last_activity_at' => 'datetime',
        'reminders_enabled' => 'boolean',
        'allowed_channels' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isLifetime(): bool
    {
        return $this->subscription_type === 'lifetime';
    }

    public function isOnTrial(): bool
    {
        return (bool) $this->is_trial && (! $this->trial_ends_at || $this->trial_ends_at->isFuture());
    }

    public function isTrialExpired(): bool
    {
        return (bool) $this->is_trial && $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function isSubscriptionExpired(): bool
    {
        if ($this->isLifetime() || empty($this->expires_at)) {
            return false;
        }

        return \Carbon\Carbon::parse($this->expires_at)->endOfDay()->isPast();
    }

    public function needsPaymentPaywall(): bool
    {
        if ($this->isLifetime()) {
            return false;
        }

        return $this->isTrialExpired() || $this->isSubscriptionExpired();
    }

    public function getTrialDaysLeft(): int
    {
        if (! $this->isOnTrial() || ! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->trial_ends_at->copy()->startOfDay(), false));
    }

    public function getIncludedUsersCount(): int
    {
        return max(1, (int) ($this->plan?->included_users ?? 1));
    }

    public function getMaxUsers(): int
    {
        return max(1, $this->getIncludedUsersCount() + (int) $this->extra_seats);
    }

    public function getActiveUsersCount(): int
    {
        return $this->users()->where('role', 'agent')->count();
    }

    public function canAddMoreUsers(): bool
    {
        return $this->getActiveUsersCount() < $this->getMaxUsers();
    }

    public function getExtraUserPrice(): float
    {
        return (float) ($this->plan?->extra_user_price ?? 49);
    }

    public function canUseEmployeeReports(): bool
    {
        if (! $this->plan) {
            return true;
        }

        return (bool) $this->plan->can_use_employee_reports;
    }

    public function applyPlan(Plan $plan, bool $syncChannels = true): void
    {
        $updates = [
            'plan_id' => $plan->id,
        ];

        if ($syncChannels) {
            $updates['reminders_enabled'] = (bool) $plan->reminders_enabled;
            $updates['allowed_channels'] = $plan->getAllowedChannels();
        }

        $this->update($updates);
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
        if (! $this->hasRemindersEnabled()) {
            return false;
        }

        return in_array($channel, $this->getAllowedChannels(), true);
    }

    public function getMonthlyEmailLimit(): ?int
    {
        if (! $this->plan || $this->plan->monthly_email_limit === null) {
            return null;
        }

        return max(0, (int) $this->plan->monthly_email_limit + (int) $this->extra_email_quota);
    }

    public function getMonthlySmsLimit(): ?int
    {
        if (! $this->plan || $this->plan->monthly_sms_limit === null) {
            return null;
        }

        return max(0, (int) $this->plan->monthly_sms_limit + (int) $this->extra_sms_quota);
    }

    public function getMonthlySentCount(string $channel, ?\Carbon\Carbon $month = null): int
    {
        $target = $month ? $month->copy() : now();

        return Reminder::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('channel', $channel)
            ->where('status', 'sent')
            ->whereBetween('created_at', [
                $target->copy()->startOfMonth(),
                $target->copy()->endOfMonth(),
            ])
            ->count();
    }

    public function getMonthlyEmailSentCount(?\Carbon\Carbon $month = null): int
    {
        return $this->getMonthlySentCount('email', $month);
    }

    public function getMonthlySmsSentCount(?\Carbon\Carbon $month = null): int
    {
        return $this->getMonthlySentCount('sms', $month);
    }

    public function getRemainingMonthlyEmails(): ?int
    {
        $limit = $this->getMonthlyEmailLimit();

        return $limit === null ? null : max(0, $limit - $this->getMonthlyEmailSentCount());
    }

    public function getRemainingMonthlySms(): ?int
    {
        $limit = $this->getMonthlySmsLimit();

        return $limit === null ? null : max(0, $limit - $this->getMonthlySmsSentCount());
    }

    public function hasMonthlyEmailQuota(): bool
    {
        $limit = $this->getMonthlyEmailLimit();

        return $limit === null || $this->getMonthlyEmailSentCount() < $limit;
    }

    public function hasMonthlySmsQuota(): bool
    {
        $limit = $this->getMonthlySmsLimit();

        return $limit === null || $this->getMonthlySmsSentCount() < $limit;
    }

    public function canSendEmailNow(): bool
    {
        return $this->canUseChannel('email') && $this->hasMonthlyEmailQuota();
    }

    public function canSendSmsNow(): bool
    {
        return $this->canUseChannel('sms') && $this->hasMonthlySmsQuota();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
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

    public function subscriptionInvoices()
    {
        return $this->hasMany(SubscriptionInvoice::class)->orderByDesc('id');
    }

    public function hasActivePayPlusStandingOrder(): bool
    {
        return filled($this->payplus_recurring_uid) && $this->payplus_status === 'active';
    }
}
