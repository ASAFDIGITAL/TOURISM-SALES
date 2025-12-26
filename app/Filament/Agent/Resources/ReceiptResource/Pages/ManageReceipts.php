<?php

namespace App\Filament\Agent\Resources\ReceiptResource\Pages;

use App\Filament\Agent\Resources\ReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageReceipts extends ManageRecords
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
