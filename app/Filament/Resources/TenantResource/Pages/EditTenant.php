<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enter_system')
                ->label(__('ui.enter_system'))
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->action(function () {
                    abort_unless(auth()->user()?->isSuperAdmin(), 403);
                    auth()->user()->startImpersonatingTenant($this->record);

                    return redirect('/agent');
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
