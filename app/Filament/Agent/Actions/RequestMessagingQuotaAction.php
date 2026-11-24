<?php

namespace App\Filament\Agent\Actions;

use App\Filament\Resources\TenantResource;
use App\Models\User;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;

class RequestMessagingQuotaAction
{
    /**
     * @return array<string, string>
     */
    public static function getBundleOptions(): array
    {
        return [
            'sms_100' => __('ui.quota_bundle_sms_100'),
            'sms_300' => __('ui.quota_bundle_sms_300'),
            'sms_1000' => __('ui.quota_bundle_sms_1000'),
            'email_500' => __('ui.quota_bundle_email_500'),
            'email_2000' => __('ui.quota_bundle_email_2000'),
            'combo_500_200' => __('ui.quota_bundle_combo'),
        ];
    }

    public static function make(string $name = 'request_messaging_quota'): Action
    {
        return Action::make($name)
            ->label(__('ui.request_more_quota_btn'))
            ->icon('heroicon-o-plus-circle')
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->getActiveTenant() !== null)
            ->modalHeading(__('ui.request_more_quota_heading'))
            ->modalDescription(function (): string {
                $tenant = auth()->user()?->getActiveTenant()?->load('plan');
                if (! $tenant) {
                    return '';
                }

                $emailLimit = $tenant->getMonthlyEmailLimit();
                $smsLimit = $tenant->getMonthlySmsLimit();

                return __('ui.request_more_quota_desc', [
                    'email_used' => $tenant->getMonthlyEmailSentCount(),
                    'email_limit' => $emailLimit ?? '∞',
                    'sms_used' => $tenant->getMonthlySmsSentCount(),
                    'sms_limit' => $smsLimit ?? '∞',
                ]);
            })
            ->modalSubmitActionLabel(__('ui.send_quota_request'))
            ->form([
                Forms\Components\Radio::make('bundle')
                    ->label(__('ui.choose_quota_bundle'))
                    ->options(fn (): array => self::getBundleOptions())
                    ->default('sms_100')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label(__('ui.notes'))
                    ->placeholder(__('ui.request_quota_notes_placeholder'))
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data, WebPushService $webPush) {
                $user = auth()->user();
                $tenant = $user?->getActiveTenant();

                if (! $tenant) {
                    return;
                }

                $bundles = self::getBundleOptions();
                $bundleLabel = $bundles[$data['bundle'] ?? ''] ?? ($data['bundle'] ?? '');
                $notes = trim((string) ($data['notes'] ?? ''));

                $tenantEditUrl = TenantResource::getUrl('edit', ['record' => $tenant->id], panel: 'admin');
                $admins = User::where('role', 'super_admin')->get();

                foreach ($admins as $admin) {
                    $originalLocale = App::getLocale();
                    $adminLocale = $admin->language ?: 'he';
                    App::setLocale($adminLocale);

                    $title = __('ui.admin_quota_request_title', ['tenant' => $tenant->name]);
                    $body = __('ui.admin_quota_request_body', [
                        'agent' => $user->name,
                        'bundle' => $bundleLabel,
                    ]) . ($notes !== '' ? "\n{$notes}" : '');

                    Notification::make()
                        ->title($title)
                        ->body($body)
                        ->icon('heroicon-o-plus-circle')
                        ->warning()
                        ->actions([
                            NotificationAction::make('manage_tenant')
                                ->label(__('ui.manage_agent'))
                                ->url($tenantEditUrl)
                                ->button(),
                        ])
                        ->sendToDatabase($admin);

                    try {
                        $webPush->sendToUser(
                            user: $admin,
                            title: $title,
                            body: $body,
                            url: $tenantEditUrl,
                            tag: "quota-request-{$tenant->id}-" . time(),
                            locale: $adminLocale,
                        );
                    } catch (\Throwable) {
                    }

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
                        actionLabel: __('ui.manage_agent'),
                        actionUrl: $tenantEditUrl,
                        tone: 'warning',
                    );

                    App::setLocale($originalLocale);
                }

                Notification::make()
                    ->success()
                    ->title(__('ui.quota_request_sent_title'))
                    ->body(__('ui.quota_request_sent_body'))
                    ->send();
            });
    }
}
