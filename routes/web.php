<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GeoJsonController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ParcelExportController;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Locale switcher
Route::get('/locale/{lang}', [LocaleController::class, 'switch'])->name('locale.switch');

// Root → redirect to login
Route::get('/', fn () => redirect()->route('login'));

// Legal pages (public — also linked from the mobile app). Content is
// CMS-managed; the static blades remain as fallback until the seeder runs.
Route::get('/privacy-policy', function () {
    $document = LegalDocument::where('key', 'privacy')->first();

    return $document === null
        ? view('legal.privacy')
        : view('legal.show', ['document' => $document]);
})->name('privacy.policy');

Route::get('/terms-of-use', function () {
    $document = LegalDocument::where('key', 'terms')->first();

    return $document === null
        ? view('legal.terms')
        : view('legal.show', ['document' => $document]);
})->name('terms.of.use');

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
    Route::get('/parcels/export/excel', [ParcelExportController::class, 'excel'])
        ->middleware('can:exports.create')->name('parcels.export.excel');
    Route::get('/parcels/export/pdf', [ParcelExportController::class, 'pdf'])
        ->middleware('can:exports.create')->name('parcels.export.pdf');
    Route::get('/parcels/{parcel}', [ParcelController::class, 'show'])->name('parcels.show');
    Route::get('/parcels/{parcel}/documents', [ParcelController::class, 'documents'])->name('parcels.documents');

    // Owners
    Route::get('/owners', fn () => view('owners.index'))->name('owners.index');

    // Survey decisions
    Route::get('/survey-decisions', fn () => view('survey-decisions.index'))->name('survey-decisions.index');

    // Documents
    Route::get('/documents', fn () => view('documents.index'))->name('documents.index');
    Route::get('/documents/{photo}/download', [DocumentController::class, 'download'])
        ->middleware('can:documents.download')
        ->name('documents.download');

    // Modification Requests
    Route::get('/modification-requests', fn () => view('modification-requests.index'))
        ->middleware('can:modification_requests.view')
        ->name('modification-requests.index');

    // Users
    Route::get('/users', fn () => view('users.index'))->name('users.index');

    // Settings (Role Manager)
    Route::get('/settings', fn () => view('settings.index'))
        ->middleware('can:roles.manage')
        ->name('settings.index');

    // Legal content editor
    Route::get('/settings/legal/{key}', [LegalDocumentController::class, 'edit'])
        ->middleware('can:roles.manage')->name('legal.edit');
    Route::post('/settings/legal/{key}', [LegalDocumentController::class, 'update'])
        ->middleware('can:roles.manage')->name('legal.update');

    // Audit Logs
    Route::get('/audit-logs', fn () => view('audit-logs.index'))
        ->middleware('can:audit_logs.view')
        ->name('audit-logs.index');

    // Profile
    Route::get('/profile', fn () => view('profile.index'))->name('profile.index');

    // GeoJSON API for map
    Route::get('/geo/parcels', [GeoJsonController::class, 'parcels'])->name('geo.parcels');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
