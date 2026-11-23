<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Payment;
use App\Models\Trip;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AgentStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = Auth::user();
        $tenantId = $user->getActiveTenantId();
        $tenant = $user->getActiveTenant();

        $canViewFinancials = $user->hasAgentPermission(\App\Models\User::PERM_VIEW_FINANCIAL_STATS);
        $canViewAllTrips = $user->hasAgentPermission(\App\Models\User::PERM_VIEW_ALL_TRIPS);

        $tripsSparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $tripsSparkline[] = Trip::where('tenant_id', $tenantId)
                ->when(! $canViewAllTrips, fn ($q) => $q->where('created_by_user_id', $user->id))
                ->whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->count();
        }

        $activeTrips = Trip::where('tenant_id', $tenantId)
            ->when(! $canViewAllTrips, fn ($q) => $q->where('created_by_user_id', $user->id))
            ->whereIn('status', ['confirmed', 'draft'])
            ->count();

        if (! $canViewFinancials) {
            $customersCount = \App\Models\Customer::where('tenant_id', $tenantId)
                ->when(! $canViewAllTrips, fn ($q) => $q->where('created_by_user_id', $user->id))
                ->count();

            $upcomingDepartures = Trip::where('tenant_id', $tenantId)
                ->when(! $canViewAllTrips, fn ($q) => $q->where('created_by_user_id', $user->id))
                ->where('status', '!=', 'cancelled')
                ->whereDate('start_date', '>=', now()->toDateString())
                ->whereDate('start_date', '<=', now()->addDays(14)->toDateString())
                ->count();

            return [
                Stat::make(__('ui.active_trips'), $activeTrips)
                    ->description(__('ui.active_trips_desc'))
                    ->icon('heroicon-o-globe-americas')
                    ->chart($tripsSparkline)
                    ->color('info'),
                Stat::make(__('ui.customers_in_crm'), $customersCount)
                    ->description(__('ui.customers'))
                    ->icon('heroicon-o-users')
                    ->color('primary'),
                Stat::make(__('ui.upcoming_departures_14d'), $upcomingDepartures)
                    ->description(__('ui.upcoming_departures'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning'),
            ];
        }

        $activeTripsQuery = Trip::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelled')
            ->with('payments')
            ->get();

        $totalBalance = $activeTripsQuery->sum(fn (Trip $t) => max(0, (float) $t->total_amount - (float) $t->payments->sum('amount')));
        $unpaidCount = $activeTripsQuery->filter(fn (Trip $t) => ((float) $t->total_amount - (float) $t->payments->sum('amount')) > 0)->count();

        $monthlyRevenue = (float) Payment::where('tenant_id', $tenantId)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $prevMonth = now()->subMonth();
        $prevMonthRevenue = (float) Payment::where('tenant_id', $tenantId)
            ->whereYear('paid_at', $prevMonth->year)
            ->whereMonth('paid_at', $prevMonth->month)
            ->sum('amount');

        $revenueSparkline = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $revenueSparkline[] = (float) Payment::where('tenant_id', $tenantId)
                ->whereYear('paid_at', $m->year)
                ->whereMonth('paid_at', $m->month)
                ->sum('amount');
        }

        $currency = $tenant?->currency ?? 'USD';
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $revenueDesc = __('ui.revenue_desc');
        if ($prevMonthRevenue > 0) {
            $diffPct = (int) round((($monthlyRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100);
            $sign = $diffPct >= 0 ? '+' : '';
            $revenueDesc = __('ui.vs_last_month', ['pct' => $sign . $diffPct . '%']);
        }

        $isLifetime = $tenant?->isLifetime() ?? false;
        $expiresAt = $tenant?->expires_at;
        $daysRemaining = (! $isLifetime && $expiresAt)
            ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($expiresAt)->startOfDay(), false)
            : null;

        return [
            Stat::make(__('ui.active_trips'), $activeTrips)
                ->description(__('ui.active_trips_desc'))
                ->icon('heroicon-o-globe-americas')
                ->chart($tripsSparkline)
                ->color('info'),
            Stat::make(__('ui.monthly_revenue'), $symbol . number_format($monthlyRevenue, 2))
                ->description($revenueDesc)
                ->icon('heroicon-o-currency-dollar')
                ->chart($revenueSparkline)
                ->color('success'),
            Stat::make(__('ui.open_balances'), $symbol . number_format($totalBalance, 2))
                ->description($unpaidCount > 0 ? __('ui.unpaid_trips_count_desc', ['count' => $unpaidCount]) : __('ui.all_balances_settled'))
                ->icon('heroicon-o-credit-card')
                ->color($totalBalance > 0 ? 'danger' : 'success'),
            Stat::make(__('ui.days_remaining'), $isLifetime ? __('ui.subscription_lifetime_short') : ($daysRemaining ?? 'N/A'))
                ->description(__('ui.subscription'))
                ->icon('heroicon-o-clock')
                ->color(match (true) {
                    $isLifetime => 'success',
                    $daysRemaining === null => 'gray',
                    $daysRemaining <= 0 => 'danger',
                    $daysRemaining <= 7 => 'warning',
                    default => 'success',
                }),
        ];
    }
}
