<?php

namespace App\Filament\Agent\Resources\AttachmentResource\Pages;

use App\Filament\Agent\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttachment extends EditRecord
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
