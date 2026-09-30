<?php

declare(strict_types=1);

use App\Http\Controllers\GeoJsonController as InternalGeoJsonController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentController;
use App\Http\Controllers\Portal\GeoJsonController;
use App\Http\Controllers\Portal\ModificationRequestController;
use App\Http\Controllers\Portal\ParcelController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\ServiceController;
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
        Route::get('/parcels/{parcel}/twin', [ParcelController::class, 'twin'])->name('parcels.twin');
        Route::get('/parcels/{parcel}/print', [ParcelController::class, 'print'])->name('parcels.print');

        Route::get('/geo/parcels', [GeoJsonController::class, 'parcels'])->name('geo.parcels');
        // Administrative boundaries are public reference geometry, not owner data — the dashboard's own endpoint serves them.
        Route::get('/geo/boundaries/{level}', [InternalGeoJsonController::class, 'boundaries'])->name('geo.boundaries');

        Route::prefix('services')->name('services.')->group(function (): void {
            Route::get('/survey-request', [ServiceController::class, 'surveyRequest'])->name('survey-request');
            Route::get('/engineering-design', [ServiceController::class, 'engineeringDesign'])->name('engineering-design');
            Route::get('/solar-energy', [ServiceController::class, 'solarEnergy'])->name('solar-energy');
            Route::get('/valuation', [ServiceController::class, 'valuation'])->name('valuation');
            Route::get('/investment', [ServiceController::class, 'investment'])->name('investment');
            Route::get('/municipal', [ServiceController::class, 'municipal'])->name('municipal');
        });

        Route::get('/documents', [DocumentController::class, 'list'])->name('documents.index');
        Route::get('/modification-requests', [ModificationRequestController::class, 'index'])->name('modification-requests.index');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::post('/parcels/{parcel}/modification-requests', [ModificationRequestController::class, 'store'])->name('parcels.modification-requests.store');
        Route::get('/parcels/{parcel}/documents', [DocumentController::class, 'index'])->name('parcels.documents');
        Route::get('/documents/{photo}/download', [DocumentController::class, 'download'])->name('documents.download');
    });
});
