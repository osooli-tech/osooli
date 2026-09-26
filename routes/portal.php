<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\GeoJsonController;
use App\Http\Controllers\Portal\ParcelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner web portal
|--------------------------------------------------------------------------
|
| A read-only, browser-based view of an owner's own data — the same phone
| + OTP sign-in the mobile app uses, through the `owner` guard (session),
| entirely separate from the dashboard's `web` guard and the mobile app's
| `sanctum` tokens. Every route here is namespaced `portal.*` and prefixed
| `/portal` so it can never collide with the internal dashboard's routes.
|
*/

Route::prefix('portal')->name('portal.')->middleware('set.locale')->group(function (): void {
    Route::middleware('guest:owner')->group(function (): void {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'requestOtp'])->name('login.submit');
        Route::get('/otp', [AuthController::class, 'showOtp'])->name('otp');
        Route::post('/otp', [AuthController::class, 'verifyOtp'])->name('otp.verify');
        Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');
    });

    Route::middleware('auth:owner')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/parcels', [ParcelController::class, 'index'])->name('parcels.index');
        Route::get('/parcels/{parcel}', [ParcelController::class, 'show'])->name('parcels.show');

        Route::get('/geo/parcels', [GeoJsonController::class, 'parcels'])->name('geo.parcels');
    });
});
