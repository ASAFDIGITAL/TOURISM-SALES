<?php

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Actions\RequestPlanUpgradeAction;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Services\PayPlusService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BillingAndInvoices extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.agent.pages.billing-and-invoices';

    public string $cycle = 'monthly';

    public int $extraSeats = 0;

    public string $couponCodeInput = '';

    public ?string $appliedCouponCode = null;

    public ?string $couponError = null;

    public static function getNavigationLabel(): string
    {
        return __('ui.billing_and_invoices');
    }

    public function getTitle(): string
    {
        return __('ui.billing_and_invoices');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->getActiveTenantId() !== null && $user->isAgencyOwner();
    }

    public function mount(): void
    {
        Plan::ensureDefaultPlansExist();

        $tenant = Auth::user()?->getActiveTenant()?->load('coupon');
        $this->extraSeats = 0;
        if ($tenant?->payplus_billing_cycle === 'yearly') {
            $this->cycle = 'yearly';
        }
        if ($tenant?->coupon && $tenant->coupon->isValidFor()) {
            $this->appliedCouponCode = $tenant->coupon->code;
            $this->couponCodeInput = $tenant->coupon->code;
        }
    }

    public function updatedExtraSeats($value): void
    {
        $this->extraSeats = max(0, min(50, (int) $value));
    }

    public function applyCoupon(): void
    {
        $this->couponError = null;
        $code = strtoupper(trim($this->couponCodeInput));

        if ($code === '') {
            $this->removeCoupon();

            return;
        }

        $coupon = Coupon::findValidByCode($code);
        if (! $coupon) {
            $this->appliedCouponCode = null;
            $this->couponError = __('ui.coupon_invalid_or_expired');

            return;
        }

        $this->appliedCouponCode = $coupon->code;
        $this->couponCodeInput = $coupon->code;

        $tenant = Auth::user()?->getActiveTenant();
        if ($tenant) {
            $tenant->update(['coupon_id' => $coupon->id]);
        }
    }

    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->couponCodeInput = '';
        $this->couponError = null;

        $tenant = Auth::user()?->getActiveTenant();
        if ($tenant && $tenant->coupon_id !== null) {
            $tenant->update(['coupon_id' => null]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Agent\Actions\RequestMessagingQuotaAction::make(),
            RequestPlanUpgradeAction::make('upgrade_plan'),
        ];
    }

    public function setCycle(string $cycle): void
    {
        $this->cycle = $cycle === 'yearly' ? 'yearly' : 'monthly';
    }

    protected function getViewData(): array
    {
        $tenant = Auth::user()?->getActiveTenant()?->load('plan');
        $payPlus = app(PayPlusService::class);
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        $invoices = $tenant
            ? SubscriptionInvoice::where('tenant_id', $tenant->id)->with('plan')->orderByDesc('id')->limit(30)->get()
            : collect();

        $activeCoupon = $this->appliedCouponCode
            ? Coupon::findValidByCode($this->appliedCouponCode)
            : null;

        $planSummaries = $plans->map(function (Plan $plan) use ($payPlus, $activeCoupon) {
            return [
                'plan' => $plan,
                'totals' => $payPlus->calculateTotals($plan, $this->extraSeats, $this->cycle, $activeCoupon),
            ];
        });

        return [
            'tenant' => $tenant,
            'payPlusConfigured' => $payPlus->isConfigured(),
            'payPlusEnv' => config('payplus.env'),
            'vatRate' => $payPlus->getVatRate(),
            'activeCoupon' => $activeCoupon,
            'planSummaries' => $planSummaries,
            'invoices' => $invoices,
        ];
    }
}
