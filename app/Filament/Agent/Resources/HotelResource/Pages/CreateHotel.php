<?php

namespace App\Filament\Agent\Resources\HotelResource\Pages;

use App\Filament\Agent\Resources\HotelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHotel extends CreateRecord
{
    protected static string $resource = HotelResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
