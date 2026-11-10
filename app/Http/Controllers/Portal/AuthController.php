<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalAccount;
use App\Services\Auth\OwnerOtpService;
use App\Support\Portal\PortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Phone + OTP sign-in for the owner portal — the same OwnerOtpService the
 * mobile app uses, through the session-based `owner` guard instead of a
 * Sanctum token. The phone signs in as a portal account; the account then
 * acts for one of the owners it opens. The pending account id lives in the
 * session between the two steps, so — unlike the mobile API, which is
 * stateless — the OTP step here never needs the phone number resubmitted.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OwnerOtpService $otp,
        private readonly PortalContext $context,
    ) {}

    public function showLogin(): View
    {
        return view('portal.auth.login');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $phone = (string) $request->string('phone');
        $throttleKey = $this->throttleKey($phone, $request->ip() ?? '');

        // The second key ignores the IP: rotating addresses must not buy more codes to guess at.
        $phoneKey = 'otp-phone:'.$this->otp->normalisePhone($phone);

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts())
            || RateLimiter::tooManyAttempts($phoneKey, $this->maxAttempts() * 2)) {
            return back()->withErrors(['phone' => __('portal.too_many_attempts')]);
        }

        RateLimiter::hit($throttleKey, 3600);
        RateLimiter::hit($phoneKey, 3600);

        $account = $this->otp->findAccountByPhone($phone);

        if ($account === null) {
            return back()->withErrors(['phone' => __('portal.phone_not_registered')]);
        }

        $this->otp->issue($account);
        session(['portal_otp_account_id' => $account->id]);

        return redirect()->route('portal.otp');
    }

    public function showOtp(): View|RedirectResponse
    {
        if (! session()->has('portal_otp_account_id')) {
            return redirect()->route('portal.login');
        }

        $phone = (string) $this->pendingAccount()?->phone;

        return view('portal.auth.otp', [
            'resendSeconds' => config('auth.mobile_otp.ttl_minutes') * 60,
            'codeLength' => OwnerOtpService::codeLength(),
            // Only the last digits, so the owner can tell which number the code went to.
            'maskedPhone' => strlen($phone) > 4 ? str_repeat('•', strlen($phone) - 4).substr($phone, -4) : null,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'string', 'digits:'.OwnerOtpService::codeLength()]]);

        $account = $this->pendingAccount();

        if ($account === null) {
            return redirect()->route('portal.login');
        }

        if (! $this->otp->verify($account, (string) $request->string('otp'))) {
            return back()->withErrors(['otp' => __('portal.otp_invalid')]);
        }

        session()->forget('portal_otp_account_id');

        // Grants can change between the two steps; sign in only to an owner
        // the account still opens.
        $owner = $account->defaultOwner();

        if ($owner === null) {
            return redirect()->route('portal.login')->withErrors(['phone' => __('portal.phone_not_registered')]);
        }

        $this->context->signIn($account, $owner);

        return redirect()->intended(route('portal.dashboard'));
    }

    public function resendOtp(): RedirectResponse
    {
        $account = $this->pendingAccount();

        if ($account === null) {
            return redirect()->route('portal.login');
        }

        $this->otp->issue($account);

        return back()->with('resent', true);
    }

    public function logout(Request $request): RedirectResponse
    {
        // The portal shares the browser session with the staff dashboard, so
        // only the owner is signed out: invalidate() would sign a staff member
        // out too and expire any dashboard page open in another tab (419).
        $this->context->signOut();
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /** The account waiting for its code, if it may still sign in. */
    private function pendingAccount(): ?PortalAccount
    {
        $accountId = session('portal_otp_account_id');
        $account = $accountId === null ? null : PortalAccount::find($accountId);

        return $account !== null && $account->is_active ? $account : null;
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
