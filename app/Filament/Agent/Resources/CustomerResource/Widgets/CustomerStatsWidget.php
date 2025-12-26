<?php

namespace App\Filament\Agent\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class CustomerStatsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $totalAmount = $this->record->trips()->sum('total_amount');
        $paidAmount = $this->record->trips->sum(fn ($trip) => $trip->payments->sum('amount'));
        $balance = $totalAmount - $paidAmount;

        $currency = $this->record->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        return [
            Stat::make(__('ui.total_amount'), $symbol . ' ' . number_format($totalAmount, 2))
                ->description(__('ui.total_spent_customer'))
                ->icon('heroicon-o-shopping-bag'),
            Stat::make(__('ui.paid'), $symbol . ' ' . number_format($paidAmount, 2))
                ->description(__('ui.total_paid_customer'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make(__('ui.balance'), $symbol . ' ' . number_format($balance, 2))
                ->description(__('ui.open_balance_customer'))
                ->icon('heroicon-o-credit-card')
                ->color($balance > 0 ? 'danger' : 'success'),
        ];
    }
}
