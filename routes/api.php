<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeedController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\LegalController;
use App\Http\Controllers\Api\V1\ListingController;
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

// Sign-in is open to the internet, so it is rate-limited per IP on top of the
// per-phone limits inside the controller.
Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('auth/request-otp', [AuthController::class, 'requestOtp'])->name('api.auth.request-otp');
    Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp'])->name('api.auth.verify-otp');
});

// Legal texts (public — shown before login)
Route::get('legal/{key}', [LegalController::class, 'show'])
    ->name('api.legal.show');

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
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

    // Parcels owners offer for sale; others see an approved one without price, deed or name.
    Route::get('listings', [ListingController::class, 'index'])->name('api.listings.index');
    Route::get('listings/mine', [ListingController::class, 'mine'])->name('api.listings.mine');
    Route::get('listings/{listing}', [ListingController::class, 'show'])->whereNumber('listing')->name('api.listings.show');
    Route::post('listings/{listing}/interest', [ListingController::class, 'interest'])->whereNumber('listing')->middleware('throttle:10,1')->name('api.listings.interest');
    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->whereNumber('listing')->name('api.listings.destroy');
    Route::post('parcels/{parcel}/listing', [ListingController::class, 'store'])->whereNumber('parcel')->middleware('throttle:10,1')->name('api.listings.store');

    Route::get('modification-requests', [ModificationRequestController::class, 'index'])->name('api.modification-requests.index');
    Route::post('modification-requests', [ModificationRequestController::class, 'store'])->name('api.modification-requests.store');
});
