<?php

namespace App\Services;

use App\Filament\Resources\SubscriptionInvoiceResource;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPlusService
{
    public function isConfigured(): bool
    {
        return filled(config('payplus.api_key'))
            && filled(config('payplus.secret_key'))
            && filled(config('payplus.payment_page_uid'));
    }

    public function getBaseUrl(): string
    {
        return config('payplus.env') === 'production'
            ? 'https://restapi.payplus.co.il/api/v1.0'
            : 'https://restapidev.payplus.co.il/api/v1.0';
    }

    public function getVatRate(): float
    {
        return (float) config('payplus.vat_rate', 18);
    }

    /**
     * Calculate net subtotal, optional coupon discount, VAT, total, and itemized PayPlus line items.
     *
     * @return array{original_subtotal: float, discount: float, subtotal: float, vat_rate: float, vat_amount: float, total: float, currency: string, items: array<int, array<string, mixed>>, coupon_applied: bool}
     */
    public function calculateTotals(Plan $plan, int $extraSeats = 0, string $cycle = 'monthly', ?Coupon $coupon = null): array
    {
        $cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';
        $extraSeats = max(0, $extraSeats);
        $vatRate = $this->getVatRate();
        $vatMultiplier = 1 + ($vatRate / 100);

        $planNet = $cycle === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;
        $seatUnitNet = $cycle === 'yearly'
            ? round((float) $plan->extra_user_price * 12 * 0.88, 2)
            : (float) $plan->extra_user_price;

        $seatsNet = round($seatUnitNet * $extraSeats, 2);
        $originalSubtotal = round($planNet + $seatsNet, 2);

        $couponApplied = $coupon !== null && $coupon->isValidFor($plan, $cycle);
        $discount = $couponApplied ? $coupon->calculateDiscount($originalSubtotal) : 0.0;
        $subtotal = round(max(0.0, $originalSubtotal - $discount), 2);
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $total = round($subtotal + $vatAmount, 2);

        $cycleLabel = $cycle === 'yearly' ? __('ui.billing_yearly') : __('ui.billing_monthly');
        $items = [
            [
                'name' => 'TOURISM-SALES — ' . $plan->getLocalizedName('he') . " ({$cycleLabel})" . ($couponApplied ? " [{$coupon->code}]" : ''),
                'quantity' => 1,
                'price' => round(max(0.0, ($planNet - $discount)) * $vatMultiplier, 2),
                'vat_type' => 0, // VAT included in item gross price
            ],
        ];

        if ($extraSeats > 0 && $seatUnitNet > 0) {
            $items[] = [
                'name' => __('ui.extra_seats_label', [], 'he') . " ({$cycleLabel})",
                'quantity' => $extraSeats,
                'price' => round($seatUnitNet * $vatMultiplier, 2),
                'vat_type' => 0,
            ];
        }

        // Ensure item sum matches exact total (avoid 1-agora rounding drift or fixed discount > planNet)
        $itemsSum = 0.0;
        foreach ($items as $item) {
            $itemsSum += ((float) $item['price']) * ((int) $item['quantity']);
        }
        $diff = round($total - $itemsSum, 2);
        if (abs($diff) > 0) {
            if (abs($diff) <= 0.10) {
                $items[0]['price'] = round(max(0.0, ((float) $items[0]['price']) + $diff), 2);
            } else {
                $items = [
                    [
                        'name' => 'TOURISM-SALES — ' . $plan->getLocalizedName('he') . " ({$cycleLabel})" . ($couponApplied ? " [{$coupon->code}]" : ''),
                        'quantity' => 1,
                        'price' => $total,
                        'vat_type' => 0,
                    ],
                ];
            }
        }

        return [
            'original_subtotal' => $originalSubtotal,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'currency' => $plan->currency ?: 'ILS',
            'items' => $items,
            'coupon_applied' => $couponApplied,
        ];
    }

    /**
     * Generate a PayPlus recurring standing-order payment link (charge_method = 3) with automatic Invoice+.
     *
     * @return array{ok: bool, payment_url?: string, page_request_uid?: string, invoice?: SubscriptionInvoice, error?: string}
     */
    public function createRecurringPaymentLink(
        Tenant $tenant,
        Plan $plan,
        string $cycle = 'monthly',
        int $extraSeats = 0,
        ?Coupon $coupon = null,
    ): array {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => __('ui.payplus_not_configured'),
            ];
        }

        $cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';
        $validCoupon = ($coupon && $coupon->isValidFor($plan, $cycle)) ? $coupon : null;
        $totals = $this->calculateTotals($plan, $extraSeats, $cycle, $validCoupon);

        $invoice = SubscriptionInvoice::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'coupon_id' => $validCoupon?->id,
            'discount_amount' => $totals['discount'],
            'billing_cycle' => $cycle,
            'status' => SubscriptionInvoice::STATUS_PENDING,
            'subtotal_amount' => $totals['subtotal'],
            'vat_rate' => $totals['vat_rate'],
            'vat_amount' => $totals['vat_amount'],
            'total_amount' => $totals['total'],
            'currency' => $totals['currency'],
            'extra_seats' => $extraSeats,
            'description' => $plan->getLocalizedName('he') . ' (' . ($cycle === 'yearly' ? 'שנתי' : 'חודשי') . ')' . ($extraSeats > 0 ? " + {$extraSeats} עובדים" : ''),
        ]);

        $issueInvoice = (bool) config('payplus.issue_invoice', true);

        $payload = [
            'payment_page_uid' => (string) config('payplus.payment_page_uid'),
            'charge_method' => 3, // Recurring Standing Order (הוראת קבע)
            'amount' => $totals['total'],
            'currency_code' => $totals['currency'],
            'sendEmailApproval' => true,
            'sendEmailFailure' => true,
            'initial_invoice' => $issueInvoice,
            'refURL_callback' => route('payplus.webhook'),
            'refURL_success' => route('payplus.success', ['invoice' => $invoice->id]),
            'refURL_failure' => route('payplus.failure', ['invoice' => $invoice->id]),
            'more_info' => (string) $tenant->id,
            'more_info_2' => (string) $plan->id,
            'more_info_3' => $cycle,
            'more_info_4' => (string) $extraSeats,
            'more_info_5' => (string) $invoice->id,
            'customer' => array_filter([
                'customer_name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
            ]),
            'items' => $totals['items'],
            'recurring_settings' => [
                'instant_first_payment' => true,
                'recurring_type' => 2, // Monthly base unit
                'recurring_range' => $cycle === 'yearly' ? 12 : 1,
                'number_of_charges' => 0, // Ongoing standing order until cancelled
                'start_date_on_payment_date' => true,
                'successful_invoice' => $issueInvoice,
                'send_customer_success_email' => true,
            ],
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'api-key' => (string) config('payplus.api_key'),
                    'secret-key' => (string) config('payplus.secret_key'),
                ])
                ->post($this->getBaseUrl() . '/PaymentPages/generateLink', $payload);

            $json = $response->json();
            $paymentLink = data_get($json, 'data.payment_page_link');
            $pageRequestUid = data_get($json, 'data.page_request_uid');

            if (! $response->successful() || blank($paymentLink)) {
                Log::warning('PayPlus generateLink failed', [
                    'status' => $response->status(),
                    'body' => $json,
                ]);

                $invoice->update([
                    'status' => SubscriptionInvoice::STATUS_FAILED,
                    'raw_payload' => is_array($json) ? $json : ['raw' => $response->body()],
                ]);

                return [
                    'ok' => false,
                    'error' => (string) (data_get($json, 'results.description') ?: __('ui.payplus_link_error')),
                ];
            }

            $invoice->update([
                'payplus_page_request_uid' => $pageRequestUid,
                'raw_payload' => is_array($json) ? $json : null,
            ]);

            return [
                'ok' => true,
                'payment_url' => (string) $paymentLink,
                'page_request_uid' => (string) $pageRequestUid,
                'invoice' => $invoice->fresh(),
            ];
        } catch (\Throwable $e) {
            Log::error('PayPlus generateLink exception: ' . $e->getMessage());

            return [
                'ok' => false,
                'error' => __('ui.payplus_link_error'),
            ];
        }
    }

    /**
     * Validate PayPlus webhook authenticity via HMAC-SHA256 Base64 signature.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $receivedHash, ?string $userAgent): bool
    {
        $secretKey = (string) config('payplus.secret_key', '');
        if ($secretKey === '' || blank($receivedHash)) {
            return false;
        }

        if ($userAgent !== null && $userAgent !== '' && $userAgent !== 'PayPlus') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $rawBody, $secretKey, true));

        return hash_equals($computed, trim($receivedHash));
    }

    /**
     * Process an authenticated PayPlus IPN/Webhook callback (initial payment OR automatic recurring renewal).
     */
    public function handleWebhookPayload(array $payload): ?SubscriptionInvoice
    {
        $tx = (array) data_get($payload, 'transaction', data_get($payload, 'data', []));
        $dataNode = (array) data_get($payload, 'data', []);

        $transactionUid = (string) ($tx['uid'] ?? $dataNode['transaction_uid'] ?? '');
        $pageRequestUid = (string) ($tx['payment_page_request_uid'] ?? $dataNode['page_request_uid'] ?? '');
        $recurringUid = (string) ($tx['recurring_charge_information']['recurring_uid']
            ?? $dataNode['recurring_uid']
            ?? data_get($payload, 'recurring_info.recurring_uid')
            ?? '');
        $customerUid = (string) ($dataNode['customer_uid'] ?? $tx['customer_uid'] ?? '');
        $statusCode = (string) ($tx['status_code'] ?? $dataNode['status_code'] ?? '');
        $isApproved = $statusCode === '000';

        $moreInfo = (array) ($tx['more_info'] ?? $dataNode);
        $tenantId = (int) ($tx['more_info'] ?? $dataNode['more_info'] ?? 0);
        $planId = (int) ($tx['more_info_2'] ?? $dataNode['more_info_2'] ?? 0);
        $cycle = (string) ($tx['more_info_3'] ?? $dataNode['more_info_3'] ?? 'monthly');
        $extraSeats = (int) ($tx['more_info_4'] ?? $dataNode['more_info_4'] ?? 0);
        $invoiceId = (int) ($tx['more_info_5'] ?? $dataNode['more_info_5'] ?? 0);

        $amount = (float) ($tx['amount'] ?? $dataNode['amount'] ?? 0);
        $currency = (string) ($tx['currency'] ?? $dataNode['currency_code'] ?? 'ILS');
        $cardLast4 = (string) ($dataNode['card_information']['four_digits'] ?? $tx['four_digits'] ?? '');

        $invoiceNumber = (string) (data_get($payload, 'invoice.number')
            ?? data_get($dataNode, 'invoice.number')
            ?? data_get($payload, 'invoice_number')
            ?? '');
        $invoiceUrl = (string) (data_get($payload, 'invoice.original_doc_url')
            ?? data_get($payload, 'invoice.doc_url')
            ?? data_get($dataNode, 'invoice.original_doc_url')
            ?? data_get($payload, 'invoice_url')
            ?? '');

        // Resolve existing invoice or tenant (handles both initial checkout & recurring renewal!)
        $existingByTx = $transactionUid !== ''
            ? SubscriptionInvoice::where('payplus_transaction_uid', $transactionUid)->first()
            : null;

        if ($existingByTx && $existingByTx->status === SubscriptionInvoice::STATUS_PAID) {
            // Idempotent replay — just backfill invoice URL if it arrived in a follow-up event
            if ($invoiceUrl !== '' && blank($existingByTx->payplus_invoice_url)) {
                $existingByTx->update([
                    'payplus_invoice_number' => $invoiceNumber ?: $existingByTx->payplus_invoice_number,
                    'payplus_invoice_url' => $invoiceUrl,
                ]);
            }

            return $existingByTx;
        }

        $invoice = $invoiceId > 0 ? SubscriptionInvoice::find($invoiceId) : null;
        if (! $invoice && $pageRequestUid !== '') {
            $invoice = SubscriptionInvoice::where('payplus_page_request_uid', $pageRequestUid)
                ->whereNull('payplus_transaction_uid')
                ->first();
        }

        $tenant = $invoice?->tenant
            ?: ($tenantId > 0 ? Tenant::find($tenantId) : null)
            ?: ($recurringUid !== '' ? Tenant::where('payplus_recurring_uid', $recurringUid)->first() : null);

        if (! $tenant) {
            Log::warning('PayPlus webhook: could not match tenant', ['payload' => $payload]);

            return null;
        }

        $plan = ($planId > 0 ? Plan::find($planId) : null) ?: $invoice?->plan ?: $tenant->plan;
        $cycle = in_array($cycle, ['monthly', 'yearly'], true) ? $cycle : ($tenant->payplus_billing_cycle ?: 'monthly');

        return DB::transaction(function () use (
            $invoice, $tenant, $plan, $cycle, $extraSeats, $isApproved,
            $transactionUid, $pageRequestUid, $recurringUid, $customerUid,
            $amount, $currency, $cardLast4, $invoiceNumber, $invoiceUrl, $payload
        ) {
            $vatRate = $this->getVatRate();
            $total = $amount > 0 ? $amount : (float) ($invoice?->total_amount ?? 0);
            $subtotal = $invoice?->subtotal_amount ? (float) $invoice->subtotal_amount : round($total / (1 + ($vatRate / 100)), 2);
            $vatAmount = $invoice?->vat_amount ? (float) $invoice->vat_amount : round($total - $subtotal, 2);

            if (! $invoice) {
                // Subsequent recurring charge — create a fresh invoice record!
                $invoice = SubscriptionInvoice::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan?->id,
                    'billing_cycle' => $cycle,
                    'status' => $isApproved ? SubscriptionInvoice::STATUS_PAID : SubscriptionInvoice::STATUS_FAILED,
                    'subtotal_amount' => $subtotal,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $total,
                    'currency' => $currency,
                    'extra_seats' => $extraSeats ?: (int) $tenant->extra_seats,
                    'payplus_page_request_uid' => $pageRequestUid ?: null,
                    'payplus_transaction_uid' => $transactionUid ?: null,
                    'payplus_recurring_uid' => $recurringUid ?: $tenant->payplus_recurring_uid,
                    'payplus_invoice_number' => $invoiceNumber ?: null,
                    'payplus_invoice_url' => $invoiceUrl ?: null,
                    'description' => ($plan?->getLocalizedName('he') ?? 'מנוי') . ' — חידוש הוראת קבע',
                    'paid_at' => $isApproved ? now() : null,
                    'raw_payload' => $payload,
                ]);
            } else {
                $invoice->update([
                    'status' => $isApproved ? SubscriptionInvoice::STATUS_PAID : SubscriptionInvoice::STATUS_FAILED,
                    'payplus_transaction_uid' => $transactionUid ?: $invoice->payplus_transaction_uid,
                    'payplus_recurring_uid' => $recurringUid ?: $invoice->payplus_recurring_uid,
                    'payplus_invoice_number' => $invoiceNumber ?: $invoice->payplus_invoice_number,
                    'payplus_invoice_url' => $invoiceUrl ?: $invoice->payplus_invoice_url,
                    'paid_at' => $isApproved ? now() : $invoice->paid_at,
                    'raw_payload' => $payload,
                ]);
            }

            if ($isApproved) {
                $baseExpiry = ($tenant->expires_at && \Carbon\Carbon::parse($tenant->expires_at)->isFuture() && ! $tenant->is_trial)
                    ? \Carbon\Carbon::parse($tenant->expires_at)
                    : now();
                $newExpiry = $cycle === 'yearly' ? $baseExpiry->copy()->addYear() : $baseExpiry->copy()->addMonth();

                $tenantUpdates = [
                    'status' => 'active',
                    'is_trial' => false,
                    'trial_ends_at' => null,
                    'expires_at' => $newExpiry->toDateString(),
                    'subscription_amount' => ((float) $tenant->subscription_amount) + $total,
                    'payplus_billing_cycle' => $cycle,
                    'payplus_status' => 'active',
                ];

                if ($plan) {
                    $tenantUpdates['plan_id'] = $plan->id;
                    $tenantUpdates['reminders_enabled'] = (bool) $plan->reminders_enabled;
                    $tenantUpdates['allowed_channels'] = $plan->getAllowedChannels();
                }

                if ($extraSeats > 0) {
                    $tenantUpdates['extra_seats'] = $extraSeats;
                }

                if ($recurringUid !== '') {
                    $tenantUpdates['payplus_recurring_uid'] = $recurringUid;
                }
                if ($customerUid !== '') {
                    $tenantUpdates['payplus_customer_uid'] = $customerUid;
                }
                if ($cardLast4 !== '') {
                    $tenantUpdates['payplus_card_last4'] = $cardLast4;
                }

                $tenant->update($tenantUpdates);

                $this->notifyPaymentSuccess($tenant, $invoice);
            } else {
                $tenant->update(['payplus_status' => 'past_due']);
                $this->notifyPaymentFailure($tenant, $invoice);
            }

            return $invoice->fresh();
        });
    }

    protected function notifyPaymentSuccess(Tenant $tenant, SubscriptionInvoice $invoice): void
    {
        $webPush = app(WebPushService::class);
        $amountStr = $invoice->getCurrencySymbol() . number_format((float) $invoice->total_amount, 2);

        $adminTitle = __('ui.payplus_admin_paid_title', ['tenant' => $tenant->name]);
        $adminBody = __('ui.payplus_admin_paid_body', [
            'amount' => $amountStr,
            'invoice' => $invoice->payplus_invoice_number ?: '#' . $invoice->id,
        ]);

        $invoiceAdminUrl = null;
        try {
            $invoiceAdminUrl = SubscriptionInvoiceResource::getUrl('index', panel: 'admin');
        } catch (\Throwable) {
            $invoiceAdminUrl = '/admin/subscription-invoices';
        }

        foreach (User::where('role', 'super_admin')->get() as $admin) {
            $notif = Notification::make()
                ->title($adminTitle)
                ->body($adminBody)
                ->icon('heroicon-o-credit-card')
                ->success();

            if ($invoiceAdminUrl) {
                $notif->actions([
                    NotificationAction::make('view_invoices')
                        ->label(__('ui.subscription_invoices'))
                        ->url($invoiceAdminUrl)
                        ->button(),
                ]);
            }

            $notif->sendToDatabase($admin);

            try {
                $webPush->sendToUser($admin, $adminTitle, $adminBody, url: $invoiceAdminUrl, tag: 'payplus-paid-' . $invoice->id);
            } catch (\Throwable) {
            }
        }

        $agentTitle = __('ui.payplus_agent_paid_title');
        $agentBody = __('ui.payplus_agent_paid_body', ['amount' => $amountStr]);
        foreach (User::where('tenant_id', $tenant->id)->where('role', 'agent')->where('agency_role', User::AGENCY_ROLE_OWNER)->get() as $owner) {
            Notification::make()
                ->title($agentTitle)
                ->body($agentBody)
                ->icon('heroicon-o-check-circle')
                ->success()
                ->sendToDatabase($owner);

            try {
                $webPush->sendToUser($owner, $agentTitle, $agentBody, url: '/agent/billing-and-invoices', tag: 'payplus-agent-paid-' . $invoice->id);
            } catch (\Throwable) {
            }
        }
    }

    protected function notifyPaymentFailure(Tenant $tenant, SubscriptionInvoice $invoice): void
    {
        $title = __('ui.payplus_admin_failed_title', ['tenant' => $tenant->name]);
        $body = __('ui.payplus_admin_failed_body', [
            'amount' => $invoice->getCurrencySymbol() . number_format((float) $invoice->total_amount, 2),
        ]);

        foreach (User::where('role', 'super_admin')->get() as $admin) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-exclamation-triangle')
                ->danger()
                ->sendToDatabase($admin);
        }
    }
}
