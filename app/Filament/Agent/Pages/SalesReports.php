<?php

namespace App\Filament\Agent\Pages;

use Filament\Pages\Page;
use App\Models\Trip;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables;
use Filament\Tables\Table;

class SalesReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.agent.pages.sales-reports';

    public ?string $fromDate = null;
    public ?string $toDate = null;

    public static function getNavigationLabel(): string
    {
        return __('ui.sales_report');
    }

    public function getTitle(): string
    {
        return __('ui.sales_report');
    }

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $currency = Auth::user()->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        // Total Revenue (Payments Collected) - filtered by Payment Date (Cash Flow)
        $revenueQuery = Payment::where('tenant_id', $tenantId);
        if ($this->fromDate) {
            $revenueQuery->whereDate('paid_at', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $revenueQuery->whereDate('paid_at', '<=', $this->toDate);
        }
        $totalRevenue = $revenueQuery->sum('amount');

        // Total Sales (Trip Total Amounts) - filtered by CREATED AT (Booking Date)
        $salesQuery = Trip::where('tenant_id', $tenantId);
        if ($this->fromDate) {
            $salesQuery->whereDate('created_at', '>=', $this->fromDate);
        }
        if ($this->toDate) {
            $salesQuery->whereDate('created_at', '<=', $this->toDate);
        }
        $salesQuery->where('status', '!=', 'cancelled');
        
        $totalSales = $salesQuery->sum('total_amount');
        $tripsCount = $salesQuery->count();

        return [
            'revenue' => $symbol . number_format($totalRevenue, 2),
            'sales' => $symbol . number_format($totalSales, 2),
            'trips_count' => $tripsCount,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Trip::where('tenant_id', Auth::user()->tenant_id)
                    ->when($this->fromDate, fn ($query) => $query->whereDate('created_at', '>=', $this->fromDate))
                    ->when($this->toDate, fn ($query) => $query->whereDate('created_at', '<=', $this->toDate))
                    ->where('status', '!=', 'cancelled')
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('ui.customer'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination')
                    ->label(__('ui.destination'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('ui.date_uploaded')) // "Date Created"
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('ui.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('ui.total_amount'))
                    ->formatStateUsing(fn ($state) => $this->formatMoney($state)),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label(__('ui.paid'))
                    ->getStateUsing(fn (Trip $record) => $record->payments->sum('amount'))
                    ->formatStateUsing(fn ($state) => $this->formatMoney($state)),
                Tables\Columns\TextColumn::make('balance')
                    ->label(__('ui.balance'))
                    ->getStateUsing(fn (Trip $record) => $record->total_amount - $record->payments->sum('amount'))
                    ->formatStateUsing(fn ($state) => $this->formatMoney($state))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("ui.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function formatMoney($amount)
    {
        $currency = Auth::user()->tenant->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
        return $symbol . ' ' . number_format($amount, 2);
    }
}
