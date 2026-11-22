<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_AGENT = 'waiting_agent';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_FEATURE = 'feature';
    public const CATEGORY_GENERAL = 'general';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'subject',
        'category',
        'priority',
        'status',
        'admin_unread_count',
        'agent_unread_count',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'admin_unread_count' => 'integer',
        'agent_unread_count' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => __('ui.ticket_status_open'),
            self::STATUS_IN_PROGRESS => __('ui.ticket_status_in_progress'),
            self::STATUS_WAITING_AGENT => __('ui.ticket_status_waiting_agent'),
            self::STATUS_CLOSED => __('ui.ticket_status_closed'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => __('ui.ticket_priority_low'),
            self::PRIORITY_NORMAL => __('ui.ticket_priority_normal'),
            self::PRIORITY_HIGH => __('ui.ticket_priority_high'),
            self::PRIORITY_URGENT => __('ui.ticket_priority_urgent'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_TECHNICAL => __('ui.ticket_category_technical'),
            self::CATEGORY_BILLING => __('ui.ticket_category_billing'),
            self::CATEGORY_FEATURE => __('ui.ticket_category_feature'),
            self::CATEGORY_GENERAL => __('ui.ticket_category_general'),
        ];
    }
}
