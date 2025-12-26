<?php

namespace App\Filament\Agent\Resources\TripResource\Pages;

use App\Filament\Agent\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
