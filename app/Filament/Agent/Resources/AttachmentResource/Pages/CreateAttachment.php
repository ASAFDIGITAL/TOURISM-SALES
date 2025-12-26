<?php

namespace App\Filament\Agent\Resources\AttachmentResource\Pages;

use App\Filament\Agent\Resources\AttachmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAttachment extends CreateRecord
{
    protected static string $resource = AttachmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
