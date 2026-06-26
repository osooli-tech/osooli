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
use Illuminate\View\View;

class OtpController extends Controller
{
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

        if (! $cached || $cached !== $request->otp) {
            return back()->withErrors(['otp' => __('auth.otp_invalid')]);
        }

        Cache::forget("otp_{$user->id}");
        session()->forget('otp_user_id');

        Auth::login($user);

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

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp_{$user->id}", $otp, now()->addMinutes(config('auth.otp.ttl_minutes')));

        app()->setLocale(session('locale', 'ar'));

        Mail::raw(
            __('auth.otp_email_body', ['otp' => $otp]),
            fn ($m) => $m->to($user->email)->subject(__('auth.otp_email_subject'))
        );

        return back()->with('resent', true);
    }
}
