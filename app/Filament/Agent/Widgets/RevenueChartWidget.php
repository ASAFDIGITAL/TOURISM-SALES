<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Payment;
use App\Models\Trip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected static ?string $maxHeight = '300px';

    public ?string $filter = '6';

    public function getHeading(): string
    {
        return __('ui.revenue_trend_6m');
    }

    public function getDescription(): ?string
    {
        return __('ui.revenue_trend_6m_desc');
    }

    protected function getFilters(): ?array
    {
        return [
            '6' => __('ui.last_6_months'),
            '12' => __('ui.last_12_months'),
        ];
    }

    protected function getData(): array
    {
        $tenantId = Auth::user()?->getActiveTenantId();
        $monthsCount = (int) ($this->filter ?: 6);

        $labels = [];
        $collected = [];
        $booked = [];

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('m/Y');

            $collectedSum = $tenantId
                ? (float) Payment::where('tenant_id', $tenantId)
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month)
                    ->sum('amount')
                : 0.0;

            $bookedSum = $tenantId
                ? (float) Trip::where('tenant_id', $tenantId)
                    ->where('status', '!=', 'cancelled')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('total_amount')
                : 0.0;

            $collected[] = round($collectedSum, 2);
            $booked[] = round($bookedSum, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => __('ui.collected_payments_series'),
                    'data' => $collected,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.85)',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1.5,
                    'borderRadius' => 7,
                ],
                [
                    'label' => __('ui.sales_volume_series'),
                    'data' => $booked,
                    'backgroundColor' => 'rgba(14, 165, 233, 0.25)',
                    'borderColor' => '#0ea5e9',
                    'borderWidth' => 2,
                    'borderRadius' => 7,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
