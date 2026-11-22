<?php

namespace App\Filament\Agent\Resources\ReminderRuleResource\Pages;

use App\Filament\Agent\Resources\ReminderRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReminderRule extends CreateRecord
{
    protected static string $resource = ReminderRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->getActiveTenantId();

        return $data;
    }
}
