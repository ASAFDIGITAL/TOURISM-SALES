<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class ExpiringTenantsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getTableHeading(): string
    {
        return __('ui.expiring_soon');
    }

    public function table(Table $table): Table
    {
        $adminCurrency = Auth::user()->currency ?? 'USD';
        
        return $table
            ->query(
                Tenant::query()
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->orderBy('expires_at', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name'))
                    ->url(fn (Tenant $record): string => \App\Filament\Resources\TenantResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('ui.expires_at'))
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_until_expiry')
                    ->label(__('ui.days_remaining'))
                    ->state(function (Tenant $record): int {
                        return now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($record->expires_at), false);
                    })
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('subscription_amount')
                    ->label(__('ui.subscription_amount'))
                    ->money($adminCurrency),
            ])
            ->actions([
                Tables\Actions\Action::make('renew')
                    ->label(__('ui.renew_subscription'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('months')
                            ->label('כמות חודשים (Months)')
                            ->numeric()
                            ->default(12)
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label(__('ui.subscription_amount'))
                            ->numeric()
                            ->prefix(match(Auth::user()->currency ?? 'USD') {
                                'ILS' => '₪',
                                'EUR' => '€',
                                'GBP' => '£',
                                default => '$',
                            })
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        $currentExpiry = $record->expires_at ? \Carbon\Carbon::parse($record->expires_at) : now();
                        if ($currentExpiry->isPast()) {
                            $currentExpiry = now();
                        }
                        
                        $record->update([
                            'expires_at' => $currentExpiry->addMonths((int)$data['months']),
                            'subscription_amount' => $record->subscription_amount + (float)$data['amount'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('ui.renewal_success'))
                            ->send();
                    })
            ]);
    }
}
