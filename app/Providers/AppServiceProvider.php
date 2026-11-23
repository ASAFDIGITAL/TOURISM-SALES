<?php

namespace App\Providers;

use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Always format currency and numbers with Western Latin digits (0-9),
        // preventing Eastern Arabic-Indic digits (١٤٩٫٠٠) when locale is Arabic on Linux ICU.
        Number::useLocale('en');
        Table::$defaultNumberLocale = 'en';
        Infolist::$defaultNumberLocale = 'en';
    }
}
