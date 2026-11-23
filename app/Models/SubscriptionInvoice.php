<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'provider',
        'billing_cycle',
        'status',
        'subtotal_amount',
        'vat_rate',
        'vat_amount',
        'total_amount',
        'currency',
        'extra_seats',
        'payplus_page_request_uid',
        'payplus_transaction_uid',
        'payplus_recurring_uid',
        'payplus_invoice_number',
        'payplus_invoice_url',
        'description',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'extra_seats' => 'integer',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function getCurrencySymbol(): string
    {
        return match ($this->currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
    }
}
