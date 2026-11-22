<?php

namespace App\Filament\Agent\Resources\ReminderRuleResource\Pages;

use App\Filament\Agent\Resources\ReminderRuleResource;
use App\Services\ReminderRuleEngine;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListReminderRules extends ListRecords
{
    protected static string $resource = ReminderRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Agent\Actions\RequestSmsAccessAction::make(),
            Actions\Action::make('create_presets')
                ->label(__('ui.create_default_rules'))
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => count(array_intersect(
                    auth()->user()?->getActiveTenant()?->getAllowedChannels() ?? [],
                    ['push', 'email', 'sms']
                )) > 0)
                ->requiresConfirmation()
                ->action(function (ReminderRuleEngine $engine) {
                    $tenantId = auth()->user()->getActiveTenantId();
                    $lang = auth()->user()->getActiveTenant()?->language ?? 'he';
                    $count = $engine->createDefaultRulesForTenant($tenantId, $lang);

                    Notification::make()
                        ->success()
                        ->title(__('ui.default_rules_created', ['count' => $count]))
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
