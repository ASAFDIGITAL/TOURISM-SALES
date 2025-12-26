<?php

namespace App\Filament\Agent\Resources\CustomerResource\Pages;

use App\Filament\Agent\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerResource\Widgets\CustomerStatsWidget::class,
        ];
    }
}
