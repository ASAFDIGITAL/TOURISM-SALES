<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Trip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TopDestinationsChartWidget extends ChartWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected static ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('ui.top_destinations_chart');
    }

    public function getDescription(): ?string
    {
        return __('ui.top_destinations_desc');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $tenantId = $user?->getActiveTenantId();
        $canViewAll = $user?->hasAgentPermission(\App\Models\User::PERM_VIEW_ALL_TRIPS) ?? true;

        $rows = $tenantId
            ? Trip::where('tenant_id', $tenantId)
                ->when(! $canViewAll, fn ($q) => $q->where('created_by_user_id', $user->id))
                ->where('status', '!=', 'cancelled')
                ->whereNotNull('destination')
                ->where('destination', '!=', '')
                ->selectRaw('destination, COUNT(*) as bookings')
                ->groupBy('destination')
                ->orderByDesc('bookings')
                ->limit(6)
                ->get()
            : collect();

        $palette = ['#f59e0b', '#0ea5e9', '#10b981', '#6366f1', '#ec4899', '#14b8a6'];

        return [
            'datasets' => [
                [
                    'label' => __('ui.bookings_count'),
                    'data' => $rows->pluck('bookings')->map(fn ($v) => (int) $v)->all(),
                    'backgroundColor' => array_slice($palette, 0, max(1, $rows->count())),
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $rows->pluck('destination')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
