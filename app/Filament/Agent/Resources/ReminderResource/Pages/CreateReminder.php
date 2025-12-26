<?php

namespace App\Filament\Agent\Resources\ReminderResource\Pages;

use App\Filament\Agent\Resources\ReminderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateReminder extends CreateRecord
{
    protected static string $resource = ReminderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
