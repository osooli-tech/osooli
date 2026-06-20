<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            if (! $user->is_active) {
                return null;
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("otp_{$user->id}", $otp, now()->addMinutes(config('auth.otp.ttl_minutes')));
            $request->session()->put('otp_user_id', $user->id);

            app()->setLocale(session('locale', 'ar'));

            Mail::raw(
                __('auth.otp_email_body', ['otp' => $otp]),
                fn ($m) => $m->to($user->email)->subject(__('auth.otp_email_subject'))
            );

            throw new HttpResponseException(redirect()->route('otp.show'));
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower($request->email) . '|' . $request->ip());
        });
    }
}
