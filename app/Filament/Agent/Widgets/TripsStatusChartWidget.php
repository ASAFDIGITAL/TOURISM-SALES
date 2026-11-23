<?php

namespace App\Filament\Agent\Widgets;

use App\Models\Trip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class TripsStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected static ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return __('ui.trips_by_status_chart');
    }

    public function getDescription(): ?string
    {
        return __('ui.trips_by_status_desc');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $tenantId = $user?->getActiveTenantId();
        $canViewAll = $user?->hasAgentPermission(\App\Models\User::PERM_VIEW_ALL_TRIPS) ?? true;

        $counts = $tenantId
            ? Trip::where('tenant_id', $tenantId)
                ->when(! $canViewAll, fn ($q) => $q->where('created_by_user_id', $user->id))
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all()
            : [];

        $statuses = [
            'confirmed' => ['label' => __('ui.confirmed'), 'color' => '#0ea5e9'],
            'completed' => ['label' => __('ui.completed'), 'color' => '#10b981'],
            'draft' => ['label' => __('ui.draft'), 'color' => '#f59e0b'],
            'cancelled' => ['label' => __('ui.cancelled'), 'color' => '#f43f5e'],
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($statuses as $key => $meta) {
            $labels[] = $meta['label'];
            $data[] = (int) ($counts[$key] ?? 0);
            $colors[] = $meta['color'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '64%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
