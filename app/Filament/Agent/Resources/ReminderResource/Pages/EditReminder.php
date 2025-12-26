<?php

namespace App\Filament\Agent\Resources\ReminderResource\Pages;

use App\Filament\Agent\Resources\ReminderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReminder extends EditRecord
{
    protected static string $resource = ReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
