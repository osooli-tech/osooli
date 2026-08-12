<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeedController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\ModificationRequestController;
use App\Http\Controllers\Api\V1\ParcelController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
|
| Serves the owners' mobile app. Prefixed with /api/v1 in bootstrap/app.php.
| Authentication is Sanctum tokens issued to `owners` — entirely separate
| from the dashboard's session auth for `users`.
|
| Contract: docs/mobile-api-contract.md
|
*/

Route::post('auth/request-otp', [AuthController::class, 'requestOtp'])->name('api.auth.request-otp');
Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp'])->name('api.auth.verify-otp');

// Legal texts (public — shown before login)
Route::get('legal/{key}', [\App\Http\Controllers\Api\V1\LegalController::class, 'show'])
    ->name('api.legal.show');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('me', [ProfileController::class, 'show'])->name('api.me');
    Route::patch('me', [ProfileController::class, 'update'])->name('api.me.update');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('api.dashboard');
    Route::get('search', [SearchController::class, 'index'])->name('api.search');

    // The literal /parcels/map must be declared before the {parcel} wildcard.
    Route::get('parcels/map', [ParcelController::class, 'map'])->name('api.parcels.map');
    Route::get('parcels', [ParcelController::class, 'index'])->name('api.parcels.index');
    Route::get('parcels/{parcel}', [ParcelController::class, 'show'])->name('api.parcels.show');
    Route::get('parcels/{parcel}/documents', [ParcelController::class, 'documents'])->name('api.parcels.documents');

    Route::get('deeds', [DeedController::class, 'index'])->name('api.deeds.index');

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('api.documents.download');

    Route::get('modification-requests', [ModificationRequestController::class, 'index'])->name('api.modification-requests.index');
    Route::post('modification-requests', [ModificationRequestController::class, 'store'])->name('api.modification-requests.store');
});
