<?php

namespace App\Filament\Agent\Resources\TripResource\Pages;

use App\Filament\Agent\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
