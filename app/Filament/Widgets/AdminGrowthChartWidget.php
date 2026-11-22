<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class AdminGrowthChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('ui.admin_growth_chart');
    }

    public function getDescription(): ?string
    {
        return __('ui.admin_growth_chart_desc');
    }

    protected function getData(): array
    {
        $labels = [];
        $newTenants = [];
        $subscriptionVolume = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('m/Y');

            $newTenants[] = Tenant::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $subscriptionVolume[] = round((float) Tenant::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('subscription_amount'), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => __('ui.new_agencies_series'),
                    'data' => $newTenants,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.85)',
                    'borderColor' => '#d97706',
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
