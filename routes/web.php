<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GeoJsonController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ParcelController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Locale switcher
Route::get('/locale/{lang}', [LocaleController::class, 'switch'])->name('locale.switch');

// Root → redirect to login
Route::get('/', fn () => redirect()->route('login'));

// OTP flow (after successful email + password)
Route::middleware('set.locale')->group(function () {
    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
});

// Authenticated routes
Route::middleware(['auth', 'user.active', 'set.locale'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Parcels
    Route::get('/parcels', fn () => view('parcels.index'))->name('parcels.index');
    Route::get('/parcels/{parcel}', [ParcelController::class, 'show'])->name('parcels.show');

    // Survey decisions
    Route::get('/survey-decisions', fn () => view('survey-decisions.index'))->name('survey-decisions.index');

    // Documents
    Route::get('/documents', fn () => view('documents.index'))->name('documents.index');
    Route::get('/documents/{photo}/download', [DocumentController::class, 'download'])
        ->middleware('can:documents.download')
        ->name('documents.download');

    // Users
    Route::get('/users', fn () => view('users.index'))->name('users.index');

    // Settings (Role Manager)
    Route::get('/settings', fn () => view('settings.index'))
        ->middleware('can:roles.manage')
        ->name('settings.index');

    // GeoJSON API for map
    Route::get('/geo/parcels', [GeoJsonController::class, 'parcels'])->name('geo.parcels');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
