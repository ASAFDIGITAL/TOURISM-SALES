<?php

use App\Http\Controllers\AgencyRegistrationController;
use App\Http\Controllers\AviationSearchController;
use App\Http\Controllers\EmailTrackingController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\PayPlusController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TenantImpersonationController;
use App\Http\Controllers\TripPortalController;
use App\Http\Controllers\UPayController;
use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

// Public Marketing Landing Page, Self-Registration & Legal Pages
Route::middleware([SetLocaleMiddleware::class])->group(function () {
    Route::get('/', [LandingController::class, 'index'])->name('landing');
    Route::get('/register', [AgencyRegistrationController::class, 'show'])->name('register.show');
    Route::post('/register', [AgencyRegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');
    Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
    Route::get('/accessibility', [LegalPageController::class, 'accessibility'])->name('legal.accessibility');
    Route::get('/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

// Convenient fallback redirect for legacy /login bookmarks
Route::redirect('/login', '/agent/login')->name('login');

Route::post('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['he', 'ar', 'en'], true), 404);
    session()->put('locale', $locale);

    if (auth()->check()) {
        auth()->user()->forceFill(['language' => $locale])->save();
    }

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

// PayPlus Recurring Standing Order & Digital Invoices (IPN Webhook + Checkout Return)
Route::post('/webhooks/payplus', [PayPlusController::class, 'webhook'])->name('payplus.webhook');

// UPay Recurring Standing Order (Server Update Webhook + Return Page)
Route::match(['get', 'post'], '/webhooks/upay', [UPayController::class, 'webhook'])->name('upay.webhook');
Route::get('/billing/upay/return', [UPayController::class, 'returnPage'])->name('upay.return');

// Email Open Tracking Pixel (1x1 transparent GIF)
Route::get('/email-track/{token}/open.gif', [EmailTrackingController::class, 'open'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('email.track.open');

// Public Branded Customer Trip Portal & 1-Click Quote Approval
Route::prefix('t/{token}')->where(['token' => '[A-Za-z0-9]+'])->name('trip.portal.')->group(function () {
    Route::get('/', [TripPortalController::class, 'show'])->name('show');
    Route::get('/itinerary', [TripPortalController::class, 'downloadItinerary'])->name('itinerary');
    Route::get('/attachment/{attachment}', [TripPortalController::class, 'downloadAttachment'])->name('attachment');
    Route::post('/approve-quote', [TripPortalController::class, 'approveQuote'])
        ->middleware('throttle:10,1')
        ->name('approve-quote');
});

Route::middleware(['auth'])->prefix('billing/payplus')->name('payplus.')->group(function () {
    Route::post('/checkout', [PayPlusController::class, 'checkout'])->name('checkout');
    Route::get('/success', [PayPlusController::class, 'success'])->name('success');
    Route::get('/failure', [PayPlusController::class, 'failure'])->name('failure');
});
