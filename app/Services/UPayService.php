<?php

namespace App\Services;

use App\Filament\Resources\TenantResource;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UPayService
{
    public function getVatRate(): float
    {
        return max(0.0, (float) config('upay.vat_rate', 18.0));
    }

    /**
     * Handle UPay Server-to-Server IPN / Webhook callback.
     *
     * @param  array<string, mixed>  $rawPayload
     * @return array{ok: bool, message: string, invoice_id?: int|null}
     */
    public function handleWebhookPayload(array $rawPayload, ?string $querySecret = null): array
    {
        $configuredSecret = trim((string) config('upay.webhook_secret', ''));
        if ($configuredSecret !== '' && ! hash_equals($configuredSecret, (string) $querySecret)) {
            return ['ok' => false, 'message' => 'Invalid webhook secret'];
        }

        // Flatten and lowercase keys for resilient parsing across UPay callback formats
        $flat = $this->flattenPayload($rawPayload);

        Log::info('UPay webhook received', ['keys' => array_keys($flat)]);

        $isFailed = $this->detectFailure($flat);
        $amount = $this->extractAmount($flat);
        $email = $this->firstFilled($flat, ['email', 'customeremail', 'clientemail', 'mail', 'payeremail', 'useremail']);
        $phone = $this->firstFilled($flat, ['phone', 'cellphone', 'customerphone', 'clientphone', 'mobile', 'tel']);
        $description = $this->firstFilled($flat, ['productname', 'description', 'itemname', 'comments', 'comment', 'title']);
        $txId = $this->firstFilled($flat, [
            'transactionid',
            'dealid',
            'upayid',
            'confirmationcode',
            'orderid',
            'paymentid',
            'id',
        ]);
        $recurringId = $this->firstFilled($flat, [
            'standingorderid',
            'recurringid',
            'token',
            'hoka_id',
        ]);
        $invoiceUrl = $this->firstFilled($flat, [
            'invoiceurl',
            'docurl',
            'receipturl',
            'documenturl',
            'invoicelink',
            'pdfurl',
        ]);
        $invoiceNumber = $this->firstFilled($flat, [
            'invoicenumber',
            'docnumber',
            'receiptnumber',
            'documentnumber',
            'invoiceno',
        ]);
        $last4 = $this->firstFilled($flat, ['last4', 'last4digits', 'cardlast4', 'fourdigits', 'cardnumber']);
        if ($last4 && strlen($last4) > 4) {
            $last4 = substr(preg_replace('/\D+/', '', $last4) ?? $last4, -4);
        }

        $tenant = $this->resolveTenant($flat, $email, $phone);
        if (! $tenant) {
            Log::warning('UPay webhook: tenant could not be matched', [
                'email' => $email,
                'phone' => $phone,
                'amount' => $amount,
                'tx_id' => $txId,
            ]);

            return ['ok' => false, 'message' => 'Tenant not matched'];
        }

        $uniqueRef = $txId ? ('upay:' . $txId) : null;
        if ($uniqueRef) {
            $existing = SubscriptionInvoice::where('payplus_transaction_uid', $uniqueRef)->first();
            if ($existing) {
                return ['ok' => true, 'message' => 'Duplicate webhook ignored', 'invoice_id' => $existing->id];
            }
        }

        [$plan, $cycle] = $this->resolvePlanAndCycle($tenant, $amount, $description);
        $vatRate = $this->getVatRate();
        $totalAmount = $amount > 0 ? $amount : $this->estimateTotal($plan, $cycle, $vatRate);
        $subtotalAmount = $vatRate > 0
            ? round($totalAmount / (1 + ($vatRate / 100)), 2)
            : round($totalAmount, 2);
        $vatAmount = round($totalAmount - $subtotalAmount, 2);

        $invoice = DB::transaction(function () use (
            $tenant,
            $plan,
            $cycle,
            $isFailed,
            $subtotalAmount,
            $vatAmount,
            $totalAmount,
            $uniqueRef,
            $invoiceNumber,
            $invoiceUrl,
            $recurringId,
            $last4,
            $description,
            $rawPayload
        ) {
            $invoice = SubscriptionInvoice::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan?->id,
                'provider' => 'upay',
                'billing_cycle' => $cycle,
                'extra_seats' => (int) $tenant->extra_seats,
                'subtotal_amount' => $subtotalAmount,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'currency' => $plan?->currency ?? $tenant->currency ?? 'ILS',
                'status' => $isFailed ? SubscriptionInvoice::STATUS_FAILED : SubscriptionInvoice::STATUS_PAID,
                'payplus_transaction_uid' => $uniqueRef,
                'payplus_invoice_number' => $invoiceNumber,
                'payplus_invoice_url' => $invoiceUrl,
                'description' => $description ?: ($plan?->getLocalizedName('he') ?? 'UPay Standing Order'),
                'raw_payload' => $rawPayload,
                'paid_at' => $isFailed ? null : now(),
            ]);

            if (! $isFailed) {
                $baseDate = ($tenant->expires_at && \Carbon\Carbon::parse($tenant->expires_at)->isFuture() && ! $tenant->is_trial)
                    ? \Carbon\Carbon::parse($tenant->expires_at)
                    : now();

                $newExpiry = $cycle === 'yearly'
                    ? $baseDate->copy()->addYear()
                    : $baseDate->copy()->addMonth();

                $updates = [
                    'status' => 'active',
                    'is_trial' => false,
                    'trial_ends_at' => null,
                    'expires_at' => $newExpiry->toDateString(),
                    'subscription_amount' => $totalAmount,
                    'payplus_billing_cycle' => $cycle,
                    'payplus_status' => 'active',
                ];

                if ($plan) {
                    $updates['plan_id'] = $plan->id;
                    $updates['reminders_enabled'] = (bool) $plan->reminders_enabled;
                    $updates['allowed_channels'] = $plan->getAllowedChannels();
                }

                if ($recurringId) {
                    $updates['payplus_recurring_uid'] = 'upay:' . $recurringId;
                }

                if ($last4) {
                    $updates['payplus_card_last4'] = $last4;
                }

                $tenant->update($updates);

                if ($tenant->coupon_id) {
                    \App\Models\Coupon::where('id', $tenant->coupon_id)->increment('used_count');
                }
            } else {
                $tenant->update(['payplus_status' => 'failed']);
            }

            return $invoice;
        });

        $this->notifyAboutPayment($tenant->fresh(), $invoice, ! $isFailed);

        return ['ok' => true, 'message' => 'Processed', 'invoice_id' => $invoice->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function flattenPayload(array $data): array
    {
        $flat = [];
        $walk = function (array $items) use (&$walk, &$flat): void {
            foreach ($items as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                } elseif ($value !== null && $value !== '') {
                    $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key) ?? (string) $key);
                    if (! isset($flat[$norm])) {
                        $flat[$norm] = trim((string) $value);
                    }
                }
            }
        };

        $walk($data);

        return $flat;
    }

    /**
     * @param  array<string, string>  $flat
     * @param  array<int, string>  $keys
     */
    private function firstFilled(array $flat, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($flat[$key])) {
                return $flat[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $flat
     */
    private function detectFailure(array $flat): bool
    {
        $errorCode = $flat['errorcode'] ?? $flat['responsecode'] ?? null;
        if ($errorCode !== null && $errorCode !== '' && $errorCode !== '0' && $errorCode !== '000') {
            return true;
        }

        $status = strtolower($flat['status'] ?? $flat['result'] ?? '');
        if ($status !== '' && in_array($status, ['failed', 'error', 'declined', 'cancel', 'cancelled', 'rejected', 'false', '0'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, string>  $flat
     */
    private function extractAmount(array $flat): float
    {
        $raw = $this->firstFilled($flat, ['amount', 'sum', 'total', 'totalamount', 'price', 'paymentamount']);
        if (! $raw) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $raw) ?? '0';

        return round(max(0.0, (float) $clean), 2);
    }

    /**
     * @param  array<string, string>  $flat
     */
    private function resolveTenant(array $flat, ?string $email, ?string $phone): ?Tenant
    {
        $explicitId = $this->firstFilled($flat, ['tenant_id', 'tenantid', 'custom', 'param1']);
        if ($explicitId && is_numeric($explicitId)) {
            $found = Tenant::with('plan')->find((int) $explicitId);
            if ($found) {
                return $found;
            }
        }

        if ($email) {
            $emailLower = strtolower(trim($email));
            $tenant = Tenant::with('plan')
                ->whereRaw('LOWER(email) = ?', [$emailLower])
                ->first();
            if ($tenant) {
                return $tenant;
            }

            $user = User::whereRaw('LOWER(email) = ?', [$emailLower])
                ->whereNotNull('tenant_id')
                ->first();
            if ($user && $user->tenant_id) {
                return Tenant::with('plan')->find($user->tenant_id);
            }
        }

        if ($phone) {
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            $tail = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
            if ($tail !== '') {
                $tenant = Tenant::with('plan')
                    ->where('phone', 'like', '%' . $tail)
                    ->first();
                if ($tenant) {
                    return $tenant;
                }

                $user = User::where('phone', 'like', '%' . $tail)
                    ->whereNotNull('tenant_id')
                    ->first();
                if ($user && $user->tenant_id) {
                    return Tenant::with('plan')->find($user->tenant_id);
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: ?Plan, 1: string}
     */
    private function resolvePlanAndCycle(Tenant $tenant, float $amount, ?string $description): array
    {
        Plan::ensureDefaultPlansExist();
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $vatMultiplier = 1 + ($this->getVatRate() / 100);

        if ($amount > 0) {
            foreach ($plans as $plan) {
                $monthlyWithVat = round((float) $plan->monthly_price * $vatMultiplier, 2);
                $yearlyWithVat = round((float) $plan->yearly_price * $vatMultiplier, 2);

                if (abs($amount - $monthlyWithVat) <= 2.5) {
                    return [$plan, 'monthly'];
                }

                if (abs($amount - $yearlyWithVat) <= 5.0) {
                    return [$plan, 'yearly'];
                }
            }
        }

        if ($description) {
            $descLower = mb_strtolower($description);
            foreach ($plans as $plan) {
                foreach ([$plan->slug, $plan->name_en, $plan->name_he] as $needle) {
                    if ($needle && str_contains($descLower, mb_strtolower($needle))) {
                        $cycle = str_contains($descLower, 'year') || str_contains($descLower, 'שנתי') ? 'yearly' : 'monthly';

                        return [$plan, $cycle];
                    }
                }
            }
        }

        return [$tenant->plan, $tenant->payplus_billing_cycle ?: 'monthly'];
    }

    private function estimateTotal(?Plan $plan, string $cycle, float $vatRate): float
    {
        if (! $plan) {
            return 0.0;
        }

        $base = $cycle === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;

        return round($base * (1 + ($vatRate / 100)), 2);
    }

    private function notifyAboutPayment(Tenant $tenant, SubscriptionInvoice $invoice, bool $approved): void
    {
        $webPush = app(WebPushService::class);
        $sym = $invoice->getCurrencySymbol();
        $amountFormatted = $sym . number_format((float) $invoice->total_amount, 2);

        $editUrl = null;
        try {
            $editUrl = TenantResource::getUrl('edit', ['record' => $tenant], panel: 'admin');
        } catch (\Throwable) {
            $editUrl = '/admin/tenants/' . $tenant->id . '/edit';
        }

        foreach (User::where('role', 'super_admin')->get() as $admin) {
            $title = $approved
                ? __('ui.payplus_admin_paid_title', ['tenant' => $tenant->name])
                : __('ui.payplus_admin_failed_title', ['tenant' => $tenant->name]);

            $body = $approved
                ? __('ui.payplus_admin_paid_body', [
                    'amount' => $amountFormatted,
                    'invoice' => $invoice->payplus_invoice_number ?: '#' . $invoice->id,
                ])
                : __('ui.payplus_admin_failed_body', ['amount' => $amountFormatted]);

            $notif = Notification::make()
                ->title($title)
                ->body($body)
                ->icon($approved ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle');

            $approved ? $notif->success() : $notif->danger();

            if ($editUrl) {
                $notif->actions([
                    NotificationAction::make('open_tenant')
                        ->label(__('ui.manage_agent'))
                        ->url($editUrl)
                        ->button(),
                ]);
            }

            $notif->sendToDatabase($admin);

            try {
                $webPush->sendToUser($admin, $title, $body, url: $editUrl, tag: 'upay-' . $invoice->id);
            } catch (\Throwable) {
            }
        }

        if ($approved) {
            $owners = User::where('tenant_id', $tenant->id)
                ->where('role', 'agent')
                ->get();

            foreach ($owners as $owner) {
                Notification::make()
                    ->title(__('ui.payplus_agent_paid_title'))
                    ->body(__('ui.payplus_agent_paid_body', ['amount' => $amountFormatted]))
                    ->icon('heroicon-o-document-check')
                    ->success()
                    ->sendToDatabase($owner);
            }
        }
    }
}
