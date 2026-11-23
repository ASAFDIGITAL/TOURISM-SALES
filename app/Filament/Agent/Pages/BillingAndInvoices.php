<?php

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Actions\RequestPlanUpgradeAction;
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

        $tenant = Auth::user()?->getActiveTenant();
        $this->extraSeats = (int) ($tenant?->extra_seats ?? 0);
        if ($tenant?->payplus_billing_cycle === 'yearly') {
            $this->cycle = 'yearly';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
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

        $planSummaries = $plans->map(function (Plan $plan) use ($payPlus) {
            return [
                'plan' => $plan,
                'totals' => $payPlus->calculateTotals($plan, $this->extraSeats, $this->cycle),
            ];
        });

        return [
            'tenant' => $tenant,
            'payPlusConfigured' => $payPlus->isConfigured(),
            'payPlusEnv' => config('payplus.env'),
            'vatRate' => $payPlus->getVatRate(),
            'planSummaries' => $planSummaries,
            'invoices' => $invoices,
        ];
    }
}
