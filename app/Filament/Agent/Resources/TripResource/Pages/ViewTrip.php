<?php

namespace App\Filament\Agent\Resources\TripResource\Pages;

use App\Filament\Agent\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
