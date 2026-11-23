<?php

namespace App\Filament\Agent\Resources\StaffResource\Pages;

use App\Filament\Agent\Resources\StaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['permissions'])) {
            $data['permissions'] = array_values((array) $data['permissions']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => StaffResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StaffResource::getUrl('index');
    }
}
