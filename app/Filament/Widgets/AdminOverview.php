<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $adminCurrency = Auth::user()->currency ?? 'USD';
        $totalRevenue = Tenant::sum('subscription_amount');
        $activeAgents = Tenant::where('status', 'active')->count();
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
            Stat::make(__('ui.total_revenue'), number_format($totalRevenue, 2) . ' ' . $adminCurrency)
                ->description(__('ui.admin_total_revenue_desc'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make(__('ui.active_agents'), $activeAgents)
                ->description(__('ui.admin_active_agents_desc'))
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
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
