<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated structured alerts (In-app + Phone Push + Customer Email/SMS)
Schedule::command('app:check-reminders')->everyFiveMinutes();
Schedule::command('app:run-reminder-rules')->hourly();
Schedule::command('app:check-upcoming-trips')->dailyAt('08:30');
Schedule::command('app:check-collection-alerts')->dailyAt('09:00');
Schedule::command('app:check-expiring-tenants')->dailyAt('09:30');
Schedule::command('app:send-trial-onboarding-emails')->dailyAt('10:00');
