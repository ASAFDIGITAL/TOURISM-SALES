<?php

namespace App\Filament\Agent\Resources\StaffResource\Pages;

use App\Filament\Agent\Actions\RequestPlanUpgradeAction;
use App\Filament\Agent\Resources\StaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    public function getSubheading(): ?string
    {
        $tenant = Auth::user()?->getActiveTenant();
        if (! $tenant) {
            return null;
        }

        $used = $tenant->getActiveUsersCount();
        $max = $tenant->getMaxUsers();
        $planName = $tenant->plan?->getLocalizedName() ?? __('ui.default_plan_name');
        $symbol = $tenant->plan?->getCurrencySymbol() ?? '₪';
        $extraPrice = number_format($tenant->getExtraUserPrice(), 0);

        return __('ui.staff_seats_subheading', [
            'plan' => $planName,
            'used' => $used,
            'max' => $max,
            'price' => $symbol . $extraPrice,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $tenant = Auth::user()?->getActiveTenant();
        $actions = [];

        if ($tenant && $tenant->canAddMoreUsers()) {
            $actions[] = Actions\CreateAction::make()
                ->label(__('ui.add_staff_member'));
        }

        $actions[] = RequestPlanUpgradeAction::make('extra_seats');

        return $actions;
    }
}
