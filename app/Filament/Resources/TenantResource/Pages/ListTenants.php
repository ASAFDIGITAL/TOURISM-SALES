<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Actions\SendAdminNotificationAction::makeHeaderAction(),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $needsRenewalScope = function (Builder $query): Builder {
            return $query
                ->where(fn (Builder $q) => $q->whereNull('subscription_type')->orWhere('subscription_type', '!=', 'lifetime'))
                ->where(function (Builder $q) {
                    $q->where(fn (Builder $t) => $t->where('is_trial', true)->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now()))
                        ->orWhere(fn (Builder $e) => $e->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(14)->toDateString()));
                });
        };

        $activeValidScope = function (Builder $query): Builder {
            return $query
                ->where('status', 'active')
                ->where(function (Builder $q) {
                    $q->where('subscription_type', 'lifetime')
                        ->orWhere(function (Builder $r) {
                            $r->where(fn (Builder $t) => $t->where('is_trial', false)->orWhereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', now()))
                                ->where(fn (Builder $e) => $e->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
                        });
                });
        };

        $trialScope = fn (Builder $query): Builder => $query
            ->where('is_trial', true)
            ->where(fn (Builder $q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', now()));

        $lifetimeScope = fn (Builder $query): Builder => $query->where('subscription_type', 'lifetime');

        $inactiveScope = fn (Builder $query): Builder => $query->whereIn('status', ['suspended', 'closed']);

        return [
            'all' => Tab::make(__('ui.tab_all_tenants'))
                ->badge(Tenant::count()),

            'active' => Tab::make(__('ui.tab_active_tenants'))
                ->badge($activeValidScope(Tenant::query())->count())
                ->badgeColor('success')
                ->modifyQueryUsing($activeValidScope),

            'needs_renewal' => Tab::make(__('ui.tab_needs_renewal'))
                ->badge($needsRenewalScope(Tenant::query())->count())
                ->badgeColor('danger')
                ->modifyQueryUsing($needsRenewalScope),

            'trial' => Tab::make(__('ui.tab_on_trial'))
                ->badge($trialScope(Tenant::query())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing($trialScope),

            'lifetime' => Tab::make(__('ui.subscription_lifetime_short'))
                ->badge($lifetimeScope(Tenant::query())->count())
                ->badgeColor('info')
                ->modifyQueryUsing($lifetimeScope),

            'inactive' => Tab::make(__('ui.tab_suspended_closed'))
                ->badge($inactiveScope(Tenant::query())->count())
                ->badgeColor('gray')
                ->modifyQueryUsing($inactiveScope),
        ];
    }
}
