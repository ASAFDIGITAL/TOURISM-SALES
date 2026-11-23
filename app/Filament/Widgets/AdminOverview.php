<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenants = Tenant::with('plan')->get();

        $activeTenants = $tenants->where('status', 'active');
        $activeAgentsCount = $activeTenants->count();
        $onTrialCount = $activeTenants->filter(fn (Tenant $t) => $t->isOnTrial())->count();
        $paidActiveTenants = $activeTenants->filter(fn (Tenant $t) => ! $t->is_trial && ! $t->isSubscriptionExpired());

        // Monthly Recurring Revenue (MRR) in ILS across active paid non-lifetime agencies
        $mrr = 0.0;
        foreach ($paidActiveTenants as $t) {
            if ($t->isLifetime()) {
                continue;
            }
            if ($t->plan) {
                $isYearly = $t->payplus_billing_cycle === 'yearly';
                $planMonthly = $isYearly
                    ? ((float) $t->plan->yearly_price / 12)
                    : (float) $t->plan->monthly_price;
                $seatsMonthly = (int) $t->extra_seats * (float) $t->plan->extra_user_price * ($isYearly ? 0.88 : 1.0);
                $mrr += $planMonthly + $seatsMonthly;
            } elseif ((float) $t->subscription_amount > 0) {
                $mrr += (float) $t->subscription_amount;
            }
        }
        $mrr = round($mrr, 0);

        $totalEvaluated = $paidActiveTenants->count() + $onTrialCount;
        $conversionRate = $totalEvaluated > 0
            ? (int) round(($paidActiveTenants->count() / $totalEvaluated) * 100)
            : 0;

        $inactive7dCount = $activeTenants->filter(function (Tenant $t): bool {
            if ($t->last_activity_at) {
                return $t->last_activity_at->lt(now()->subDays(7));
            }

            return $t->created_at !== null && $t->created_at->lt(now()->subDays(7));
        })->count();

        $expiringSoon = Tenant::where(function ($q) {
            $q->whereNull('subscription_type')
                ->orWhere('subscription_type', '!=', 'lifetime');
        })
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        $openTickets = SupportTicket::where('status', '!=', SupportTicket::STATUS_CLOSED)->count();
        $unreadTickets = (int) SupportTicket::sum('admin_unread_count');

        return [
            Stat::make(__('ui.admin_mrr_label'), '₪' . number_format($mrr, 0))
                ->description(__('ui.admin_mrr_desc', ['paid' => $paidActiveTenants->count()]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('ui.active_agents'), $activeAgentsCount)
                ->description(__('ui.admin_trial_conversion_desc', ['trial' => $onTrialCount, 'rate' => $conversionRate]))
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make(__('ui.admin_inactive_7d'), $inactive7dCount)
                ->description(__('ui.admin_inactive_7d_desc'))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($inactive7dCount > 0 ? 'danger' : 'success'),

            Stat::make(__('ui.expiring_soon'), $expiringSoon)
                ->description(__('ui.admin_expiring_soon_desc'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSoon > 0 ? 'warning' : 'gray'),

            Stat::make(__('ui.open_support_tickets'), $openTickets)
                ->description($unreadTickets > 0 ? __('ui.unread_count_badge', ['count' => $unreadTickets]) : __('ui.all_tickets_read'))
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($unreadTickets > 0 ? 'danger' : ($openTickets > 0 ? 'warning' : 'success'))
                ->url(SupportTicketResource::getUrl('index')),
        ];
    }
}
