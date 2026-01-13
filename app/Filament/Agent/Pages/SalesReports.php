<?php

namespace App\Filament\Agent\Pages;

use Filament\Pages\Page;
use App\Models\Trip;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.agent.pages.sales-reports';

    public ?string $fromDate = null;
    public ?string $toDate = null;

    public static function getNavigationLabel(): string
    {
        return __('ui.sales_report');
    }

    public function getTitle(): string
    {
        return __('ui.sales_report');
    }

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $currency = Auth::user()->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        // Total Revenue (Payments Collected)
        $revenueQuery = Payment::where('tenant_id', $tenantId);
        if ($this->fromDate) {
            $revenueQuery->whereDate('paid_at', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $revenueQuery->whereDate('paid_at', '<=', $this->toDate);
        }
        $totalRevenue = $revenueQuery->sum('amount');

        // Total Sales (Trip Total Amounts)
        // Ensure we filter by trip date, typically start_date
        $salesQuery = Trip::where('tenant_id', $tenantId);
        if ($this->fromDate) {
            $salesQuery->whereDate('start_date', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $salesQuery->whereDate('start_date', '<=', $this->toDate);
        }
        // Exclude cancelled trips from sales figures? Usually yes.
        $salesQuery->where('status', '!=', 'cancelled');
        
        $totalSales = $salesQuery->sum('total_amount');

        return [
            'revenue' => $symbol . number_format($totalRevenue, 2),
            'sales' => $symbol . number_format($totalSales, 2),
        ];
    }
}
