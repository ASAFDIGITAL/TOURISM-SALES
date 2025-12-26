<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Trip;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AgentStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;

        $totalBalance = Trip::where('tenant_id', $tenantId)->get()->sum('balance');
        $monthlyRevenue = Payment::where('tenant_id', $tenantId)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');
        $activeTrips = Trip::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'draft'])
            ->count();

        $currency = Auth::user()->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $expiresAt = Auth::user()->tenant->expires_at;
        $daysRemaining = $expiresAt ? now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($expiresAt)->startOfDay(), false) : null;

        return [
            Stat::make(__('ui.active_trips'), $activeTrips)
                ->description(__('ui.active_trips_desc'))
                ->icon('heroicon-o-globe-americas'),
            Stat::make(__('ui.monthly_revenue'), $symbol . number_format($monthlyRevenue, 2))
                ->description(__('ui.revenue_desc'))
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make(__('ui.open_balances'), $symbol . number_format($totalBalance, 2))
                ->description(__('ui.balance_desc'))
                ->icon('heroicon-o-credit-card')
                ->color($totalBalance > 0 ? 'danger' : 'success'),
            Stat::make(__('ui.days_remaining'), $daysRemaining ?? 'N/A')
                ->description(__('ui.subscription'))
                ->icon('heroicon-o-clock')
                ->color(match(true) {
                    $daysRemaining === null => 'gray',
                    $daysRemaining <= 0 => 'danger',
                    $daysRemaining <= 7 => 'warning',
                    default => 'success',
                }),
        ];
    }
}
