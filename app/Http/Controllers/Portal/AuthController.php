<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Phone + OTP sign-in for the owner portal — the same OwnerOtpService the
 * mobile app uses, through the session-based `owner` guard instead of a
 * Sanctum token. The pending owner id lives in the session between the two
 * steps, so — unlike the mobile API, which is stateless — the OTP step here
 * never needs the phone number resubmitted.
 */
class AuthController extends Controller
{
    public function __construct(private readonly OwnerOtpService $otp) {}

    public function showLogin(): View
    {
        return view('portal.auth.login');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = (string) $request->string('phone');
        $throttleKey = $this->throttleKey($phone, $request->ip() ?? '');

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts())) {
            return back()->withErrors(['phone' => __('portal.too_many_attempts')]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $owner = $this->otp->findOwnerByPhone($phone);

        if ($owner === null) {
            return back()->withErrors(['phone' => __('portal.phone_not_registered')]);
        }

        $this->otp->issue($owner);
        session(['portal_otp_owner_id' => $owner->id]);

        return redirect()->route('portal.otp');
    }

    public function showOtp(): View|RedirectResponse
    {
        if (! session()->has('portal_otp_owner_id')) {
            return redirect()->route('portal.login');
        }

        return view('portal.auth.otp', ['resendSeconds' => config('auth.mobile_otp.ttl_minutes') * 60]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'string', 'digits_between:4,6']]);

        $owner = $this->pendingOwner();

        if ($owner === null) {
            return redirect()->route('portal.login');
        }

        if (! $this->otp->verify($owner, (string) $request->string('otp'))) {
            return back()->withErrors(['otp' => __('portal.otp_invalid')]);
        }

        session()->forget('portal_otp_owner_id');
        Auth::guard('owner')->login($owner);
        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function resendOtp(): RedirectResponse
    {
        $owner = $this->pendingOwner();

        if ($owner === null) {
            return redirect()->route('portal.login');
        }

        $this->otp->issue($owner);

        return back()->with('resent', true);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('owner')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    private function pendingOwner(): ?Owner
    {
        $ownerId = session('portal_otp_owner_id');

        return $ownerId === null ? null : Owner::find($ownerId);
    }

    /** Throttles per phone number and IP, so one attacker cannot lock out others. */
    private function throttleKey(string $phone, string $ip): string
    {
        return 'portal-otp-request:'.$this->otp->normalisePhone($phone).'|'.$ip;
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.mobile_otp.max_attempts_per_hour', 5);
    }
}
