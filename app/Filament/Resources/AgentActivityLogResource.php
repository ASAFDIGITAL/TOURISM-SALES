<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentActivityLogResource\Pages;
use App\Models\AgentActivityLog;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AgentActivityLogResource extends Resource
{
    protected static ?string $model = AgentActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('ui.activity_log_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.online_and_logs');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.online_and_logs');
    }

    public static function getNavigationBadge(): ?string
    {
        $onlineCount = User::onlineAgents(5)->count();

        return $onlineCount > 0 ? (string) $onlineCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'tenant']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.date_time'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label(__('ui.event_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AgentActivityLog::EVENT_LOGIN => __('ui.log_event_login'),
                        AgentActivityLog::EVENT_LOGIN_GOOGLE => __('ui.log_event_login_google'),
                        AgentActivityLog::EVENT_LOGIN_OTP => __('ui.log_event_login_otp'),
                        AgentActivityLog::EVENT_LOGIN_FAILED => __('ui.log_event_login_failed'),
                        AgentActivityLog::EVENT_LOGOUT => __('ui.log_event_logout'),
                        AgentActivityLog::EVENT_IP_CHANGED => __('ui.log_event_ip_changed'),
                        AgentActivityLog::EVENT_RECORD_DELETED => __('ui.log_event_record_deleted'),
                        AgentActivityLog::EVENT_IMPERSONATE_START => __('ui.log_event_impersonate_start'),
                        AgentActivityLog::EVENT_IMPERSONATE_END => __('ui.log_event_impersonate_end'),
                        default => __('ui.log_event_active'),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        AgentActivityLog::EVENT_LOGIN,
                        AgentActivityLog::EVENT_LOGIN_GOOGLE,
                        AgentActivityLog::EVENT_LOGIN_OTP => 'success',
                        AgentActivityLog::EVENT_LOGIN_FAILED,
                        AgentActivityLog::EVENT_RECORD_DELETED => 'danger',
                        AgentActivityLog::EVENT_IP_CHANGED,
                        AgentActivityLog::EVENT_IMPERSONATE_START => 'warning',
                        AgentActivityLog::EVENT_LOGOUT,
                        AgentActivityLog::EVENT_IMPERSONATE_END => 'gray',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('ui.agent'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('ui.name'))
                    ->description(fn (AgentActivityLog $record): ?string => $record->user?->email)
                    ->searchable(['name', 'email'])
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('ui.ip_address'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('device_label')
                    ->label(__('ui.device_browser'))
                    ->badge()
                    ->color('gray')
                    ->tooltip(fn (AgentActivityLog $record): ?string => $record->user_agent)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('details')
                    ->label(__('ui.audit_details'))
                    ->limit(40)
                    ->tooltip(fn (AgentActivityLog $record): ?string => $record->details)
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('path')
                    ->label(__('ui.current_page'))
                    ->fontFamily('mono')
                    ->limit(30)
                    ->tooltip(fn (AgentActivityLog $record): ?string => $record->path)
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('ui.agent'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label(__('ui.name'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('event')
                    ->label(__('ui.event_type'))
                    ->options([
                        AgentActivityLog::EVENT_LOGIN => __('ui.log_event_login'),
                        AgentActivityLog::EVENT_LOGIN_GOOGLE => __('ui.log_event_login_google'),
                        AgentActivityLog::EVENT_LOGIN_OTP => __('ui.log_event_login_otp'),
                        AgentActivityLog::EVENT_LOGIN_FAILED => __('ui.log_event_login_failed'),
                        AgentActivityLog::EVENT_LOGOUT => __('ui.log_event_logout'),
                        AgentActivityLog::EVENT_IP_CHANGED => __('ui.log_event_ip_changed'),
                        AgentActivityLog::EVENT_RECORD_DELETED => __('ui.log_event_record_deleted'),
                        AgentActivityLog::EVENT_IMPERSONATE_START => __('ui.log_event_impersonate_start'),
                        AgentActivityLog::EVENT_IMPERSONATE_END => __('ui.log_event_impersonate_end'),
                        AgentActivityLog::EVENT_ACTIVE => __('ui.log_event_active'),
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('ui.start_date'))
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('ui.end_date'))
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['from'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when(filled($data['until'] ?? null), fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\Action::make('open_tenant')
                    ->label(__('ui.manage_agent'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (AgentActivityLog $record): bool => filled($record->tenant_id))
                    ->url(fn (AgentActivityLog $record): string => TenantResource::getUrl('edit', ['record' => $record->tenant_id])),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentActivityLogs::route('/'),
        ];
    }
}
