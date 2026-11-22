<?php

namespace App\Filament\Agent\Widgets;

use App\Filament\Agent\Pages\MobileAppNotifications;
use App\Filament\Agent\Pages\SalesReports;
use App\Filament\Agent\Resources\CustomerResource;
use App\Filament\Agent\Resources\ReminderRuleResource;
use App\Filament\Agent\Resources\TripResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Trip;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardHeroWidget extends Widget
{
    protected static string $view = 'filament.agent.widgets.dashboard-hero-widget';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $user = Auth::user();
        $tenant = $user?->getActiveTenant();
        $tenantId = $user?->getActiveTenantId();

        $hour = now()->hour;
        $greetingKey = match (true) {
            $hour >= 5 && $hour < 12 => 'ui.greeting_morning',
            $hour >= 12 && $hour < 18 => 'ui.greeting_afternoon',
            default => 'ui.greeting_evening',
        };

        $customersCount = $tenantId ? Customer::where('tenant_id', $tenantId)->count() : 0;

        $upcomingDeparturesCount = $tenantId
            ? Trip::where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('start_date', '>=', now()->toDateString())
                ->whereDate('start_date', '<=', now()->addDays(14)->toDateString())
                ->count()
            : 0;

        $totalSales = $tenantId
            ? (float) Trip::where('tenant_id', $tenantId)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount')
            : 0.0;

        $totalCollected = $tenantId
            ? (float) Payment::where('tenant_id', $tenantId)->sum('amount')
            : 0.0;

        $collectionRate = $totalSales > 0
            ? (int) min(100, round(($totalCollected / $totalSales) * 100))
            : 100;

        $currency = $tenant?->currency ?? 'ILS';
        $symbol = match ($currency) {
            'ILS' => '₪',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };

        $remindersEnabled = $tenant?->hasRemindersEnabled() ?? true;

        return [
            'greeting' => __($greetingKey),
            'userName' => $user?->name ?? '',
            'agencyName' => $tenant?->name ?? 'TOURISM-SALES',
            'todayLabel' => now()->translatedFormat('d/m/Y'),
            'customersCount' => $customersCount,
            'upcomingDeparturesCount' => $upcomingDeparturesCount,
            'collectionRate' => $collectionRate,
            'totalCollectedFormatted' => $symbol . number_format($totalCollected, 0),
            'remindersEnabled' => $remindersEnabled,
            'urls' => [
                'new_trip' => TripResource::getUrl('create'),
                'new_customer' => CustomerResource::getUrl('create'),
                'sales_reports' => SalesReports::getUrl(),
                'reminder_rules' => $remindersEnabled ? ReminderRuleResource::getUrl('index') : null,
                'mobile_alerts' => MobileAppNotifications::getUrl(),
            ],
        ];
    }
}
