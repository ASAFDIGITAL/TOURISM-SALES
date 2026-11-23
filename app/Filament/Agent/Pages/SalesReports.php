<?php

namespace App\Filament\Agent\Pages;

use App\Models\Payment;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

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

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAgentPermission(\App\Models\User::PERM_VIEW_SALES_REPORTS) ?? false;
    }

    public function getEmployeePerformance(): array
    {
        $user = Auth::user();
        $tenant = $user?->getActiveTenant();
        $tenantId = $user?->getActiveTenantId();

        if (! $tenantId || ! $user?->isAgencyOwner() || ! ($tenant?->canUseEmployeeReports() ?? true)) {
            return [];
        }

        $currency = $tenant?->currency ?? 'ILS';
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $trips = Trip::with(['payments', 'createdBy'])
            ->where('tenant_id', $tenantId)
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', $this->toDate))
            ->where('status', '!=', 'cancelled')
            ->get();

        $grouped = $trips->groupBy(fn (Trip $t) => $t->created_by_user_id ?: 0);

        $rows = [];
        foreach ($grouped as $userId => $userTrips) {
            $repName = $userTrips->first()?->createdBy?->name ?? __('ui.unassigned_rep');
            $sales = (float) $userTrips->sum('total_amount');
            $cost = (float) $userTrips->sum('supplier_cost');
            $profit = round($sales - $cost, 2);
            $collected = (float) $userTrips->sum(fn (Trip $t) => (float) $t->payments->sum('amount'));
            $balance = max(0, $sales - $collected);
            $rate = $sales > 0 ? (int) min(100, round(($collected / $sales) * 100)) : 100;

            $rows[] = [
                'name' => $repName,
                'trips_count' => $userTrips->count(),
                'sales_raw' => $sales,
                'sales' => $symbol . number_format($sales, 2),
                'profit_raw' => $profit,
                'profit' => $symbol . number_format($profit, 2),
                'collected' => $symbol . number_format($collected, 2),
                'balance' => $symbol . number_format($balance, 2),
                'balance_raw' => $balance,
                'rate' => $rate,
            ];
        }

        usort($rows, fn ($a, $b) => $b['sales_raw'] <=> $a['sales_raw']);

        return $rows;
    }

    public function mount(): void
    {
        $this->fromDate = now()->startOfMonth()->format('Y-m-d');
        $this->toDate = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label(__('ui.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(fn () => $this->downloadPdf()),
            Action::make('download_csv')
                ->label(__('ui.download_csv'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    public function downloadPdf()
    {
        $tenant = Auth::user()->getActiveTenant();
        $tenantId = Auth::user()->getActiveTenantId();

        $trips = Trip::with(['customer', 'payments'])
            ->where('tenant_id', $tenantId)
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', $this->toDate))
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $revenueQuery = Payment::where('tenant_id', $tenantId)
            ->when($this->fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('paid_at', '<=', $this->toDate));

        $totalRevenue = (float) $revenueQuery->sum('amount');
        $totalSales = (float) $trips->sum('total_amount');
        $totalBalance = (float) $trips->sum(fn (Trip $t) => (float) $t->total_amount - (float) $t->payments->sum('amount'));
        $tripsCount = $trips->count();

        $content = Pdf::loadView('pdf.sales-report', [
            'tenant' => $tenant,
            'trips' => $trips,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'totalRevenue' => $totalRevenue,
            'totalSales' => $totalSales,
            'totalBalance' => $totalBalance,
            'tripsCount' => $tripsCount,
        ])
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->output();

        $suffix = ($this->fromDate ?: 'all') . '_' . ($this->toDate ?: 'all');

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, "sales-report-{$suffix}.pdf");
    }

    public function downloadCsv()
    {
        $tenantId = Auth::user()->getActiveTenantId();
        $trips = Trip::with(['customer', 'payments'])
            ->where('tenant_id', $tenantId)
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', $this->toDate))
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get();

        $suffix = ($this->fromDate ?: 'all') . '_' . ($this->toDate ?: 'all');

        return response()->streamDownload(function () use ($trips) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for proper Hebrew/Arabic opening in Excel
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('ui.customer'),
                __('ui.trip'),
                __('ui.destination'),
                __('ui.supplier_name'),
                __('ui.creation_date'),
                __('ui.start_date'),
                __('ui.total_amount'),
                __('ui.supplier_cost'),
                __('ui.net_profit'),
                __('ui.paid'),
                __('ui.balance'),
                __('ui.status'),
            ]);

            foreach ($trips as $trip) {
                $paid = (float) $trip->payments->sum('amount');
                $total = (float) $trip->total_amount;
                $cost = (float) $trip->supplier_cost;
                fputcsv($out, [
                    $trip->customer?->name ?? '',
                    $trip->title,
                    $trip->destination ?? '',
                    $trip->supplier_name ?? '',
                    $trip->created_at?->format('d/m/Y') ?? '',
                    $trip->start_date?->format('d/m/Y') ?? '',
                    number_format($total, 2, '.', ''),
                    number_format($cost, 2, '.', ''),
                    number_format($total - $cost, 2, '.', ''),
                    number_format($paid, 2, '.', ''),
                    number_format($total - $paid, 2, '.', ''),
                    __("ui.{$trip->status}"),
                ]);
            }

            fclose($out);
        }, "sales-report-{$suffix}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getStats(): array
    {
        $tenantId = Auth::user()->getActiveTenantId();
        $currency = Auth::user()->getActiveTenant()?->currency ?? 'USD';
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
        
        $totalSales = (float) $salesQuery->sum('total_amount');
        $totalSupplierCost = (float) (clone $salesQuery)->sum('supplier_cost');
        $totalNetProfit = round($totalSales - $totalSupplierCost, 2);
        $avgMargin = $totalSales > 0 ? round(($totalNetProfit / $totalSales) * 100, 1) : 0.0;
        $tripsCount = (clone $salesQuery)->count();

        return [
            'revenue' => $symbol . number_format($totalRevenue, 2),
            'sales' => $symbol . number_format($totalSales, 2),
            'supplier_cost' => $symbol . number_format($totalSupplierCost, 2),
            'net_profit' => $symbol . number_format($totalNetProfit, 2),
            'margin' => $avgMargin,
            'trips_count' => $tripsCount,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Trip::where('tenant_id', Auth::user()->getActiveTenantId())
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
                Tables\Columns\TextColumn::make('supplier_cost')
                    ->label(__('ui.supplier_cost'))
                    ->formatStateUsing(fn ($state) => $this->formatMoney((float) $state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('net_profit')
                    ->label(__('ui.net_profit'))
                    ->getStateUsing(fn (Trip $record) => $record->net_profit)
                    ->formatStateUsing(fn ($state) => $this->formatMoney((float) $state))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
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
                        'quote' => 'primary',
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function formatMoney($amount)
    {
        $currency = Auth::user()->getActiveTenant()?->currency ?? 'USD';
        $symbol = match($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
        return $symbol . ' ' . number_format($amount, 2);
    }
}
