<?php

use App\Http\Controllers\AviationSearchController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TenantImpersonationController;
use Illuminate\Support\Facades\Route;

// All routing logic is now handled by Filament panels
// Admin at / and Agent at /agent

Route::post('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['he', 'ar', 'en'], true), 404);
    session()->put('locale', $locale);

    return back();
})->name('locale.switch');

Route::middleware(['auth'])->prefix('admin/impersonate')->name('admin.impersonate.')->group(function () {
    Route::post('/tenant/{tenant}', [TenantImpersonationController::class, 'enter'])
        ->name('enter');
    Route::post('/switch', [TenantImpersonationController::class, 'switchTenant'])
        ->name('switch');
    Route::post('/leave', [TenantImpersonationController::class, 'leave'])
        ->name('leave');
});

Route::middleware(['auth'])->prefix('push')->name('push.')->group(function () {
    Route::post('/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('subscribe');
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('unsubscribe');
    Route::post('/test', [PushSubscriptionController::class, 'sendTest'])->name('test');
});

Route::middleware(['auth'])->prefix('aviation')->name('aviation.')->group(function () {
    Route::get('/airports', [AviationSearchController::class, 'airports'])->name('airports');
    Route::get('/airlines', [AviationSearchController::class, 'airlines'])->name('airlines');
    Route::get('/destinations', [AviationSearchController::class, 'destinations'])->name('destinations');
});
