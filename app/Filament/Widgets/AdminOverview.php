<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $adminCurrency = Auth::user()->currency ?? 'USD';
        $totalRevenue = Tenant::sum('subscription_amount');
        $activeAgents = Tenant::where('status', 'active')->count();
        $expiringSoon = Tenant::where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(30))
            ->count();

        return [
            Stat::make(__('ui.total_revenue'), number_format($totalRevenue, 2) . ' ' . $adminCurrency)
                ->description('Total collected subscriptions')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make(__('ui.active_agents'), $activeAgents)
                ->description('Agents with active status')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
            Stat::make(__('ui.expiring_soon'), $expiringSoon)
                ->description('Ending within 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSoon > 0 ? 'warning' : 'gray'),
        ];
    }
}
