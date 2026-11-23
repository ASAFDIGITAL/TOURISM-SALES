<?php

namespace App\Filament\Agent\Resources\StaffResource\Pages;

use App\Filament\Agent\Resources\StaffResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    public function mount(): void
    {
        $tenant = Auth::user()?->getActiveTenant();
        if (! $tenant || ! $tenant->canAddMoreUsers()) {
            Notification::make()
                ->title(__('ui.seat_limit_reached_title'))
                ->body(__('ui.seat_limit_reached_body'))
                ->warning()
                ->send();

            $this->redirect(StaffResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Auth::user()?->getActiveTenant();

        $data['tenant_id'] = $tenant?->id;
        $data['role'] = 'agent';
        $data['agency_role'] = User::AGENCY_ROLE_STAFF;
        $data['language'] = $tenant?->language ?? 'he';
        $data['currency'] = $tenant?->currency ?? 'ILS';
        $data['permissions'] = array_values((array) ($data['permissions'] ?? []));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return StaffResource::getUrl('index');
    }
}
