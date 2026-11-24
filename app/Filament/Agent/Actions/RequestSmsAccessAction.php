<?php

namespace App\Filament\Agent\Actions;

use App\Models\User;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class RequestSmsAccessAction
{
    public static function make(): Action
    {
        return Action::make('request_sms_access')
            ->label(__('ui.request_sms_access'))
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->color('primary')
            ->outlined()
            ->visible(function (): bool {
                $tenant = auth()->user()?->getActiveTenant();

                return $tenant !== null && !$tenant->canUseChannel('sms');
            })
            ->modalHeading(__('ui.request_sms_modal_heading'))
            ->modalDescription(__('ui.request_sms_modal_desc'))
            ->modalSubmitActionLabel(__('ui.send_request'))
            ->form([
                Forms\Components\Textarea::make('note')
                    ->label(__('ui.request_sms_note_label'))
                    ->placeholder(__('ui.request_sms_note_placeholder'))
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                self::dispatchRequest(trim((string) ($data['note'] ?? '')));
            });
    }

    public static function dispatchRequest(string $note = ''): bool
    {
        $user = auth()->user();
        $tenant = $user?->getActiveTenant();

        if (!$user || !$tenant) {
            return false;
        }

        $cooldownKey = 'sms-access-request:' . $tenant->id;
        if (Cache::has($cooldownKey)) {
            Notification::make()
                ->warning()
                ->title(__('ui.request_sms_already_sent'))
                ->send();

            return false;
        }

        $admins = User::where('role', 'super_admin')->get();
        $editUrl = \App\Filament\Resources\TenantResource::getUrl('edit', ['record' => $tenant->id], panel: 'admin');
        $webPush = app(WebPushService::class);

        foreach ($admins as $admin) {
            $originalLocale = App::getLocale();
            $locale = $admin->language ?: 'he';
            App::setLocale($locale);

            $title = __('ui.admin_sms_request_title', ['tenant' => $tenant->name]);
            $body = __('ui.admin_sms_request_body', [
                'tenant' => $tenant->name,
                'user' => $user->name,
            ]);
            if ($note !== '') {
                $body .= "\n" . __('ui.notes') . ': ' . $note;
            }

            Notification::make()
                ->title($title)
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->warning()
                ->body($body)
                ->actions([
                    NotificationAction::make('enable_sms')
                        ->label(__('ui.open_agent_to_enable'))
                        ->url($editUrl, shouldOpenInNewTab: true),
                ])
                ->sendToDatabase($admin);

            $webPush->sendToUser(
                user: $admin,
                title: $title,
                body: $body,
                url: $editUrl,
                tag: "sms-request-tenant-{$tenant->id}",
                locale: $locale,
            );

            app(\App\Services\AdminAlertMailService::class)->sendToAdmin(
                admin: $admin,
                subject: $title,
                body: $body,
                badge: __('ui.admin_alert_badge_request'),
                details: [
                    __('ui.agency_name') => $tenant->name,
                    __('ui.name') => $user->name,
                    __('ui.phone') => $user->phone ?: ($tenant->phone ?? ''),
                    __('ui.email') => $user->email,
                ],
                actionLabel: __('ui.open_agent_to_enable'),
                actionUrl: $editUrl,
                tone: 'warning',
            );

            App::setLocale($originalLocale);
        }

        Cache::put($cooldownKey, true, now()->addMinutes(10));

        Notification::make()
            ->success()
            ->title(__('ui.request_sms_sent_success'))
            ->send();

        return true;
    }
}
