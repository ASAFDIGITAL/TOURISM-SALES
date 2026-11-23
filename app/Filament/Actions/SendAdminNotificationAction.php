<?php

namespace App\Filament\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Services\InforuSmsService;
use App\Services\WebPushService;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendAdminNotificationAction
{
    public static function makeTableAction(): TableAction
    {
        return TableAction::make('send_admin_notification')
            ->label(__('ui.send_notification_to_agent'))
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->modalHeading(fn (Tenant $record): string => __('ui.send_notification_modal_title', ['name' => $record->name]))
            ->modalSubmitActionLabel(__('ui.send_now'))
            ->form(self::getFormSchema(includeAudiencePicker: false))
            ->action(function (Tenant $record, array $data): void {
                self::dispatchNotifications(collect([$record]), $data);
            });
    }

    public static function makeHeaderAction(): PageAction
    {
        return PageAction::make('broadcast_admin_notification')
            ->label(__('ui.send_notification_to_agents'))
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->modalHeading(__('ui.send_notification_to_agents'))
            ->modalSubmitActionLabel(__('ui.send_now'))
            ->form(self::getFormSchema(includeAudiencePicker: true))
            ->action(function (array $data): void {
                $audience = (string) ($data['audience'] ?? 'all');
                $tenants = match ($audience) {
                    'selected' => Tenant::whereIn('id', (array) ($data['tenant_ids'] ?? []))->get(),
                    'trial' => Tenant::where('status', 'active')->where('is_trial', true)->get(),
                    'paid' => Tenant::where('status', 'active')->where('is_trial', false)->get(),
                    'by_plan' => Tenant::where('status', 'active')->where('plan_id', $data['plan_id'] ?? 0)->get(),
                    default => Tenant::where('status', 'active')->get(),
                };

                self::dispatchNotifications($tenants, $data);
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function getFormSchema(bool $includeAudiencePicker): array
    {
        $schema = [];

        if ($includeAudiencePicker) {
            $schema[] = Forms\Components\Radio::make('audience')
                ->label(__('ui.send_to_label'))
                ->options([
                    'all' => __('ui.all_active_agents'),
                    'trial' => __('ui.tab_on_trial'),
                    'paid' => __('ui.paid_agencies_only'),
                    'by_plan' => __('ui.by_plan_option'),
                    'selected' => __('ui.specific_agents'),
                ])
                ->default('all')
                ->columns(2)
                ->live()
                ->required();

            $schema[] = Forms\Components\Select::make('plan_id')
                ->label(__('ui.plan'))
                ->options(fn (): array => \App\Models\Plan::query()->orderBy('sort_order')->pluck('name_he', 'id')->all())
                ->visible(fn (Get $get): bool => $get('audience') === 'by_plan')
                ->required(fn (Get $get): bool => $get('audience') === 'by_plan');

            $schema[] = Forms\Components\Select::make('tenant_ids')
                ->label(__('ui.select_agents'))
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                ->visible(fn (Get $get): bool => $get('audience') === 'selected')
                ->required(fn (Get $get): bool => $get('audience') === 'selected');
        }

        $schema[] = Forms\Components\TextInput::make('title')
            ->label(__('ui.notification_title'))
            ->placeholder(__('ui.notification_title_placeholder'))
            ->required()
            ->maxLength(160);

        $schema[] = Forms\Components\Textarea::make('body')
            ->label(__('ui.message'))
            ->placeholder(__('ui.notification_body_placeholder'))
            ->rows(4)
            ->required()
            ->maxLength(1500);

        $schema[] = Forms\Components\Select::make('style')
            ->label(__('ui.type'))
            ->options([
                'info' => __('ui.announcement_info'),
                'success' => __('ui.announcement_success'),
                'warning' => __('ui.announcement_warning'),
                'danger' => __('ui.announcement_danger'),
            ])
            ->default('info')
            ->required();

        $schema[] = Forms\Components\CheckboxList::make('channels')
            ->label(__('ui.delivery_channels'))
            ->options([
                'database' => __('ui.admin_notify_channel_bell'),
                'push' => __('ui.admin_notify_channel_push'),
                'sms' => __('ui.admin_notify_channel_sms'),
                'email' => __('ui.admin_notify_channel_email'),
            ])
            ->default(['database', 'push'])
            ->columns(2)
            ->required();

        return $schema;
    }

    /**
     * @param Collection<int, Tenant> $tenants
     * @param array<string, mixed> $data
     */
    public static function dispatchNotifications(Collection $tenants, array $data): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $style = (string) ($data['style'] ?? 'info');
        $channels = (array) ($data['channels'] ?? ['database', 'push']);

        $webPush = app(WebPushService::class);
        $smsService = app(InforuSmsService::class);

        $bellCount = 0;
        $pushCount = 0;
        $smsCount = 0;
        $emailCount = 0;

        $sentPhones = [];
        $sentEmails = [];

        foreach ($tenants as $tenant) {
            $users = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('role', 'agent')
                ->get();

            foreach ($users as $user) {
                if (in_array('database', $channels, true)) {
                    $notif = Notification::make()
                        ->title($title)
                        ->body($body)
                        ->icon('heroicon-o-megaphone');

                    match ($style) {
                        'success' => $notif->success(),
                        'warning' => $notif->warning(),
                        'danger' => $notif->danger(),
                        default => $notif->info(),
                    };

                    $notif->sendToDatabase($user);
                    $bellCount++;
                }

                if (in_array('push', $channels, true)) {
                    try {
                        $pushCount += $webPush->sendToUser(
                            $user,
                            $title,
                            $body,
                            url: '/agent',
                            tag: 'admin-broadcast-' . time(),
                        );
                    } catch (\Throwable) {
                        // Keep broadcast resilient
                    }
                }

                if (in_array('sms', $channels, true) && filled($user->phone)) {
                    $phoneKey = preg_replace('/\D+/', '', (string) $user->phone);
                    if ($phoneKey && ! isset($sentPhones[$phoneKey])) {
                        $sentPhones[$phoneKey] = true;
                        try {
                            $res = $smsService->sendSms((string) $user->phone, "{$title}\n{$body}");
                            if ((is_array($res) && ! empty($res['success'])) || $res === true) {
                                $smsCount++;
                            }
                        } catch (\Throwable) {
                        }
                    }
                }

                if (in_array('email', $channels, true) && filled($user->email)) {
                    $emailKey = strtolower(trim((string) $user->email));
                    if (! isset($sentEmails[$emailKey])) {
                        $sentEmails[$emailKey] = true;
                        try {
                            Mail::to($emailKey)->send(new \App\Mail\TrialOnboardingMail(
                                tenant: $tenant,
                                step: 'broadcast',
                                subjectLine: $title,
                                heading: $title,
                                introText: $body,
                                highlights: [],
                                ctaLabel: __('ui.drip_day0_cta'),
                                ctaUrl: rtrim((string) config('app.url', 'https://tourism.asafdigital.com'), '/') . '/agent',
                                localeCode: in_array($user->language, ['he', 'ar', 'en'], true) ? $user->language : ($tenant->language ?: 'he'),
                            ));
                            $emailCount++;
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            // Fallback to agency phone/email if SMS/Email requested and no user had one
            if (in_array('sms', $channels, true) && filled($tenant->phone)) {
                $phoneKey = preg_replace('/\D+/', '', (string) $tenant->phone);
                if ($phoneKey && ! isset($sentPhones[$phoneKey])) {
                    $sentPhones[$phoneKey] = true;
                    try {
                        $res = $smsService->sendSms((string) $tenant->phone, "{$title}\n{$body}");
                        if ((is_array($res) && ! empty($res['success'])) || $res === true) {
                            $smsCount++;
                        }
                    } catch (\Throwable) {
                    }
                }
            }

            if (in_array('email', $channels, true) && filled($tenant->email)) {
                $emailKey = strtolower(trim((string) $tenant->email));
                if (! isset($sentEmails[$emailKey])) {
                    $sentEmails[$emailKey] = true;
                    try {
                        Mail::to($emailKey)->send(new \App\Mail\TrialOnboardingMail(
                            tenant: $tenant,
                            step: 'broadcast',
                            subjectLine: $title,
                            heading: $title,
                            introText: $body,
                            highlights: [],
                            ctaLabel: __('ui.drip_day0_cta'),
                            ctaUrl: rtrim((string) config('app.url', 'https://tourism.asafdigital.com'), '/') . '/agent',
                            localeCode: in_array($tenant->language, ['he', 'ar', 'en'], true) ? $tenant->language : 'he',
                        ));
                        $emailCount++;
                    } catch (\Throwable) {
                    }
                }
            }
        }

        Notification::make()
            ->title(__('ui.admin_notification_sent_title'))
            ->body(__('ui.admin_notification_sent_summary', [
                'bell' => $bellCount,
                'push' => $pushCount,
                'sms' => $smsCount,
                'email' => $emailCount,
            ]))
            ->success()
            ->send();
    }
}
