<?php

namespace App\Filament\Resources\AgentActivityLogResource\Pages;

use App\Filament\Resources\AgentActivityLogResource;
use App\Filament\Widgets\OnlineAgentsWidget;
use App\Models\AgentActivityLog;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAgentActivityLogs extends ListRecords
{
    protected static string $resource = AgentActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_audit_csv')
                ->label(__('ui.export_audit_csv'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $records = $this->getFilteredTableQuery()
                        ->with(['user', 'tenant'])
                        ->orderByDesc('created_at')
                        ->limit(5000)
                        ->get();

                    $filename = 'security-audit-logs-' . now()->format('Y-m-d-Hi') . '.csv';

                    return response()->streamDownload(function () use ($records): void {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, [
                            'Timestamp',
                            'Event',
                            'Agency',
                            'User',
                            'Email',
                            'IP Address',
                            'Device',
                            'Path',
                            'Details',
                            'User-Agent',
                        ]);

                        foreach ($records as $log) {
                            fputcsv($out, [
                                $log->created_at?->format('Y-m-d H:i:s'),
                                $log->event,
                                $log->tenant?->name ?? '-',
                                $log->user?->name ?? '-',
                                $log->user?->email ?? '-',
                                $log->ip_address ?? '-',
                                $log->device_label ?? '-',
                                $log->path ?? '-',
                                $log->details ?? '-',
                                $log->user_agent ?? '-',
                            ]);
                        }

                        fclose($out);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OnlineAgentsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('ui.tab_all_tenants')),
            'logins' => Tab::make(__('ui.tab_logins'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', [
                    AgentActivityLog::EVENT_LOGIN,
                    AgentActivityLog::EVENT_LOGIN_GOOGLE,
                    AgentActivityLog::EVENT_LOGIN_OTP,
                ]))
                ->badgeColor('success'),
            'security' => Tab::make(__('ui.tab_security_alerts'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', [
                    AgentActivityLog::EVENT_LOGIN_FAILED,
                    AgentActivityLog::EVENT_IP_CHANGED,
                ]))
                ->badgeColor('danger'),
            'audit' => Tab::make(__('ui.tab_deletions_and_access'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('event', [
                    AgentActivityLog::EVENT_RECORD_DELETED,
                    AgentActivityLog::EVENT_IMPERSONATE_START,
                    AgentActivityLog::EVENT_IMPERSONATE_END,
                ]))
                ->badgeColor('warning'),
            'logouts' => Tab::make(__('ui.tab_logouts'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('event', AgentActivityLog::EVENT_LOGOUT))
                ->badgeColor('gray'),
        ];
    }
}
