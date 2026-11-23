<?php

namespace App\Filament\Resources\TrialEmailTemplateResource\Pages;

use App\Filament\Actions\SendAdminNotificationAction;
use App\Filament\Resources\TrialEmailTemplateResource;
use App\Models\TrialEmailTemplate;
use App\Services\TrialOnboardingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTrialEmailTemplates extends ListRecords
{
    protected static string $resource = TrialEmailTemplateResource::class;

    public function mount(): void
    {
        TrialEmailTemplate::ensureDefaultsExist();
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            SendAdminNotificationAction::makeHeaderAction()
                ->label(__('ui.broadcast_update_email_sms_btn')),

            Actions\Action::make('run_auto_drip_now')
                ->label(__('ui.run_auto_trial_drip_now'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (TrialOnboardingService $service) {
                    $sent = $service->processAllTrialTenants();

                    Notification::make()
                        ->success()
                        ->title(__('ui.template_sent_success', ['count' => $sent]))
                        ->send();
                }),
        ];
    }
}
