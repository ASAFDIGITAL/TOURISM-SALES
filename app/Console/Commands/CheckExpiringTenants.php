<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\WebPushService;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class CheckExpiringTenants extends Command
{
    protected $signature = 'app:check-expiring-tenants';

    protected $description = 'Notify super admins (in-app & phone push) about recurring tenants expiring within 30 days';

    public function handle(WebPushService $webPushService): int
    {
        $expiringTenants = Tenant::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscription_type')
                    ->orWhere('subscription_type', '!=', 'lifetime');
            })
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', Carbon::now()->addDays(30)->format('Y-m-d'))
            ->orderBy('expires_at', 'asc')
            ->get();

        $this->info("Found {$expiringTenants->count()} recurring tenants expiring within 30 days.");

        if ($expiringTenants->isEmpty()) {
            return self::SUCCESS;
        }

        $admins = User::where('role', 'super_admin')
            ->where('notify_expiring_tenants', true)
            ->get();

        foreach ($expiringTenants as $tenant) {
            $daysLeft = (int) now()->startOfDay()->diffInDays(Carbon::parse($tenant->expires_at)->startOfDay(), false);
            $editUrl = \App\Filament\Resources\TenantResource::getUrl('edit', ['record' => $tenant->id], panel: 'admin');

            foreach ($admins as $admin) {
                $originalLocale = App::getLocale();
                $locale = $admin->language ?: 'he';
                App::setLocale($locale);

                $title = __('ui.admin_expiring_alert_title', ['tenant' => $tenant->name]);
                $body = $daysLeft <= 0
                    ? __('ui.admin_expiring_alert_expired', ['tenant' => $tenant->name, 'date' => Carbon::parse($tenant->expires_at)->format('d/m/Y')])
                    : __('ui.admin_expiring_alert_body', [
                        'tenant' => $tenant->name,
                        'days' => $daysLeft,
                        'date' => Carbon::parse($tenant->expires_at)->format('d/m/Y'),
                    ]);

                Notification::make()
                    ->title($title)
                    ->icon('heroicon-o-clock')
                    ->warning()
                    ->body($body)
                    ->actions([
                        Action::make('manage')
                            ->label(__('ui.manage_agent'))
                            ->url($editUrl, shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($admin);

                $webPushService->sendToUser(
                    user: $admin,
                    title: $title,
                    body: $body,
                    url: $editUrl,
                    tag: "admin-expiring-tenant-{$tenant->id}",
                    locale: $locale,
                );

                App::setLocale($originalLocale);
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
