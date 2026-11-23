<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TripMessageService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class InactiveTenantsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('ui.inactive_tenants_widget_heading');
    }

    public function table(Table $table): Table
    {
        $cutoff = now()->subDays(7);

        return $table
            ->query(
                Tenant::query()
                    ->with('plan')
                    ->where('status', 'active')
                    ->where(function (Builder $q) use ($cutoff) {
                        $q->where('last_activity_at', '<', $cutoff)
                            ->orWhere(fn (Builder $never) => $never
                                ->whereNull('last_activity_at')
                                ->where('created_at', '<', $cutoff));
                    })
                    ->orderByRaw('COALESCE(last_activity_at, created_at) ASC')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.agent'))
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.name_he')
                    ->label(__('ui.plan'))
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('ui.phone'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label(__('ui.last_activity_label'))
                    ->formatStateUsing(fn ($state): string => $state
                        ? \Carbon\Carbon::parse($state)->diffForHumans()
                        : __('ui.never_logged_in'))
                    ->badge()
                    ->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp_checkin')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                    ->color('success')
                    ->visible(fn (Tenant $record): bool => filled($record->phone))
                    ->url(function (Tenant $record): string {
                        $msg = __('ui.admin_inactive_wa_checkin', ['agency' => $record->name]);

                        return app(TripMessageService::class)->buildWhatsAppUrl((string) $record->phone, $msg);
                    }, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('manage')
                    ->label(__('ui.manage_agent'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Tenant $record): string => TenantResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading(__('ui.all_agencies_active_recently'))
            ->paginated([5, 10]);
    }
}
