<?php

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agent')
            ->path('agent')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->passwordReset()
            ->brandName('TOURISM-SALES')
            ->brandLogo(fn () => asset('images/logo-light.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-dark.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn () => asset('images/favicon.png'))
            ->font('Open Sans', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => [
                    ...Color::Amber,
                    400 => '251, 191, 36',
                    500 => '245, 158, 11',
                    600 => '245, 158, 11',
                ],
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.pwa-head')->render() . view('filament.components.crm-theme')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_BEFORE,
                fn (): string => view('filament.agent.components.impersonation-banner')->render(),
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => view('filament.agent.components.system-announcements-banner')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.agent.components.support-floating-widget')->render(),
            )
            ->discoverResources(in: app_path('Filament/Agent/Resources'), for: 'App\\Filament\\Agent\\Resources')
            ->discoverPages(in: app_path('Filament/Agent/Pages'), for: 'App\\Filament\\Agent\\Pages')
            ->pages([
                \App\Filament\Agent\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agent/Widgets'), for: 'App\\Filament\\Agent\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                \App\Http\Middleware\SetLocaleMiddleware::class,
                \App\Http\Middleware\RedirectIfWrongPanel::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
