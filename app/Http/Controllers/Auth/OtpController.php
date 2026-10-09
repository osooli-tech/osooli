<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OtpController extends Controller
{
    /** Wrong codes allowed before the emailed code is thrown away. */
    private const MAX_VERIFY_ATTEMPTS = 5;

    private const MAX_RESENDS_PER_HOUR = 5;

    public function show(): View|RedirectResponse
    {
        if (! session()->has('otp_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.otp', ['resendSeconds' => config('auth.otp.resend_seconds')]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $userId = session('otp_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $cached = Cache::get("otp_{$user->id}");

        if (! $cached || ! hash_equals((string) $cached, (string) $request->otp)) {
            // Guesses are counted; after a few wrong ones the code is burnt.
            if ($cached && Cache::increment("otp_attempts_{$user->id}") >= self::MAX_VERIFY_ATTEMPTS) {
                Cache::forget("otp_{$user->id}");
                Cache::forget("otp_attempts_{$user->id}");
            }

            return back()->withErrors(['otp' => __('auth.otp_invalid')]);
        }

        Cache::forget("otp_{$user->id}");
        Cache::forget("otp_attempts_{$user->id}");
        session()->forget('otp_user_id');

        Auth::login($user);
        // A fresh session id on sign-in, so one planted beforehand is worthless.
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function resend(): RedirectResponse
    {
        $userId = session('otp_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $resendKey = "otp-resend:{$user->id}";
        if (RateLimiter::tooManyAttempts($resendKey, self::MAX_RESENDS_PER_HOUR)) {
            return back()->withErrors(['otp' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($resendKey)])]);
        }
        RateLimiter::hit($resendKey, 3600);

        $otp = $user->fixedTestOtp() ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp_{$user->id}", $otp, now()->addMinutes(config('auth.otp.ttl_minutes')));
        Cache::forget("otp_attempts_{$user->id}");

        app()->setLocale(session('locale', 'ar'));

        Mail::raw(
            __('auth.otp_email_body', ['otp' => $otp]),
            fn ($m) => $m->to($user->email)->subject(__('auth.otp_email_subject'))
        );

        return back()->with('resent', true);
    }
}
