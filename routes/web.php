<?php

use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// تبديل اللغة
Route::get('/locale/{lang}', [LocaleController::class, 'switch'])->name('locale.switch');

// الصفحة الرئيسية → تسجيل الدخول
Route::get('/', fn () => redirect()->route('login'));

// OTP (بعد إدخال email+password الصحيح)
Route::middleware('set.locale')->group(function () {
    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
});

// Dashboard وباقي الصفحات (تُضاف لاحقاً)
Route::middleware(['auth', 'user.active', 'set.locale'])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
