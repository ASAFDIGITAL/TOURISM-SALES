<?php

namespace App\Filament\Agent\Actions;

use App\Filament\Resources\TenantResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\WebPushService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class RequestPlanUpgradeAction
{
    public static function make(string $defaultType = 'extra_seats'): Action
    {
        return Action::make('request_plan_upgrade')
            ->label(__('ui.request_seats_or_upgrade'))
            ->icon('heroicon-o-user-plus')
            ->color('warning')
            ->modalHeading(__('ui.request_seats_or_upgrade_heading'))
            ->modalDescription(function (): string {
                $tenant = Auth::user()?->getActiveTenant();
                if (! $tenant) {
                    return '';
                }

                $symbol = $tenant->plan?->getCurrencySymbol() ?? '₪';
                $price = number_format($tenant->getExtraUserPrice(), 0);

                return __('ui.request_seats_or_upgrade_desc', [
                    'used' => $tenant->getActiveUsersCount(),
                    'max' => $tenant->getMaxUsers(),
                    'price' => $symbol . $price,
                ]);
            })
            ->modalSubmitActionLabel(__('ui.send_request'))
            ->form([
                Forms\Components\Radio::make('request_type')
                    ->label(__('ui.upgrade_request_type'))
                    ->options([
                        'extra_seats' => __('ui.upgrade_type_extra_seats'),
                        'upgrade_plan' => __('ui.upgrade_type_change_plan'),
                    ])
                    ->default($defaultType)
                    ->live()
                    ->required(),

                Forms\Components\TextInput::make('seats_requested')
                    ->label(__('ui.how_many_extra_seats'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(50)
                    ->default(1)
                    ->visible(fn (Get $get): bool => $get('request_type') === 'extra_seats')
                    ->required(fn (Get $get): bool => $get('request_type') === 'extra_seats'),

                Forms\Components\Select::make('target_plan_id')
                    ->label(__('ui.select_target_plan'))
                    ->options(fn (): array => Plan::where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn (Plan $p) => [
                        $p->id => $p->getLocalizedName() . ' — ' . $p->getCurrencySymbol() . number_format((float) $p->monthly_price, 0) . '/' . __('ui.month_short'),
                    ])->all())
                    ->visible(fn (Get $get): bool => $get('request_type') === 'upgrade_plan')
                    ->required(fn (Get $get): bool => $get('request_type') === 'upgrade_plan'),

                Forms\Components\Textarea::make('note')
                    ->label(__('ui.request_sms_note_label'))
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                $user = Auth::user();
                $tenant = $user?->getActiveTenant();
                if (! $user || ! $tenant) {
                    return;
                }

                $cacheKey = 'plan_upgrade_request_cooldown_' . $tenant->id;
                if (Cache::has($cacheKey)) {
                    Notification::make()
                        ->title(__('ui.upgrade_request_already_sent'))
                        ->info()
                        ->send();

                    return;
                }

                $detail = ($data['request_type'] ?? 'extra_seats') === 'upgrade_plan'
                    ? __('ui.upgrade_detail_plan', [
                        'plan' => Plan::find($data['target_plan_id'] ?? null)?->getLocalizedName() ?? '-',
                    ])
                    : __('ui.upgrade_detail_seats', [
                        'seats' => (int) ($data['seats_requested'] ?? 1),
                    ]);

                $note = trim((string) ($data['note'] ?? ''));
                $title = __('ui.admin_upgrade_request_title', ['tenant' => $tenant->name]);
                $body = __('ui.admin_upgrade_request_body', [
                    'tenant' => $tenant->name,
                    'user' => $user->name,
                    'detail' => $detail,
                ]) . ($note !== '' ? "\n{$note}" : '');

                $editUrl = null;
                try {
                    $editUrl = TenantResource::getUrl('edit', ['record' => $tenant], panel: 'admin');
                } catch (\Throwable) {
                    $editUrl = '/admin/tenants/' . $tenant->id . '/edit';
                }

                $webPush = app(WebPushService::class);
                $admins = User::query()->where('role', 'super_admin')->get();

                foreach ($admins as $admin) {
                    $notif = Notification::make()
                        ->title($title)
                        ->body($body)
                        ->icon('heroicon-o-sparkles')
                        ->warning();

                    if ($editUrl) {
                        $notif->actions([
                            NotificationAction::make('open_tenant')
                                ->label(__('ui.open_agent_to_enable'))
                                ->url($editUrl)
                                ->button(),
                        ]);
                    }

                    $notif->sendToDatabase($admin);

                    try {
                        $webPush->sendToUser(
                            $admin,
                            $title,
                            $body,
                            url: $editUrl,
                            tag: 'upgrade-request-' . $tenant->id,
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
                        actionLabel: __('ui.open_agent_to_enable'),
                        actionUrl: $editUrl,
                        tone: 'warning',
                    );
                }

                Cache::put($cacheKey, true, now()->addMinutes(5));

                Notification::make()
                    ->title(__('ui.upgrade_request_sent_success'))
                    ->success()
                    ->send();
            });
    }
}
