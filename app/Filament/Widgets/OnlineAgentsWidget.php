<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AgentActivityLogResource;
use App\Filament\Resources\TenantResource;
use App\Models\AgentActivityLog;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OnlineAgentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        $count = User::onlineAgents(5)->count();

        return __('ui.online_agents_widget_heading', ['count' => $count]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::onlineAgents(5)
                    ->with('tenant')
                    ->orderByDesc('last_seen_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('online_status')
                    ->label(__('ui.status'))
                    ->state(fn (): string => __('ui.online_now_badge'))
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-signal'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->weight('bold')
                    ->description(fn (User $record): string => (string) $record->email)
                    ->searchable(['name', 'email']),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('ui.agent'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('last_ip')
                    ->label(__('ui.ip_address'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('device')
                    ->label(__('ui.device_browser'))
                    ->state(fn (User $record): string => AgentActivityLog::parseDeviceLabel($record->last_user_agent))
                    ->tooltip(fn (User $record): ?string => $record->last_user_agent)
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('last_page')
                    ->label(__('ui.current_page'))
                    ->fontFamily('mono')
                    ->limit(32)
                    ->tooltip(fn (User $record): ?string => $record->last_page)
                    ->placeholder('/agent'),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label(__('ui.last_seen'))
                    ->formatStateUsing(fn ($state): string => $state ? \Carbon\Carbon::parse($state)->diffForHumans() : '-')
                    ->tooltip(fn (User $record): ?string => $record->last_seen_at?->format('d/m/Y H:i:s'))
                    ->color('success'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('view_all_logs')
                    ->label(__('ui.view_all_activity_logs'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->url(fn (): string => AgentActivityLogResource::getUrl('index')),
            ])
            ->actions([
                Tables\Actions\Action::make('tenant_logs')
                    ->label(__('ui.online_and_logs'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('gray')
                    ->visible(fn (User $record): bool => filled($record->tenant_id))
                    ->url(fn (User $record): string => AgentActivityLogResource::getUrl('index', [
                        'tableFilters' => [
                            'tenant_id' => ['value' => $record->tenant_id],
                        ],
                    ])),
                Tables\Actions\Action::make('manage_tenant')
                    ->label(__('ui.manage_agent'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (User $record): bool => filled($record->tenant_id))
                    ->url(fn (User $record): string => TenantResource::getUrl('edit', ['record' => $record->tenant_id])),
            ])
            ->emptyStateHeading(__('ui.no_agents_online_now'))
            ->emptyStateDescription(__('ui.no_agents_online_desc'))
            ->emptyStateIcon('heroicon-o-signal-slash')
            ->paginated([5, 10, 25]);
    }
}
