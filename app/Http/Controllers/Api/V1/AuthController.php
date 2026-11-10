<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\PortalAccountResource;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(private readonly OwnerOtpService $otp) {}

    /** Sends a one-time code to a registered owner's phone. */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $phone = (string) $request->validated('phone');
        $throttleKey = $this->throttleKey($phone, (string) $request->ip());

        // The second key ignores the IP: rotating addresses must not buy more codes to guess at.
        $phoneKey = 'otp-phone:'.$this->otp->normalisePhone($phone);

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts())
            || RateLimiter::tooManyAttempts($phoneKey, $this->maxAttempts() * 2)) {
            return $this->respondError(__('api.too_many_attempts'), Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($throttleKey, 3600);
        RateLimiter::hit($phoneKey, 3600);

        $account = $this->otp->findAccountByPhone($phone);

        if ($account === null) {
            return $this->respondError(__('api.phone_not_registered'), Response::HTTP_NOT_FOUND);
        }

        return $this->respond([
            'message' => __('api.otp_sent'),
            'expires_in' => $this->otp->issue($account),
        ]);
    }

    /** Exchanges a valid code for an API token. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $account = $this->otp->findAccountByPhone((string) $request->validated('phone'));

        if ($account === null || ! $this->otp->verify($account, (string) $request->validated('otp'))) {
            return $this->respondInvalid('otp', __('api.otp_invalid'));
        }

        $owner = $account->defaultOwner();

        if ($owner === null) {
            return $this->respondInvalid('otp', __('api.otp_invalid'));
        }

        // Only one device per account may be signed in at a time: logging in
        // here revokes whatever token an earlier device of this account was
        // holding, so its next request gets a 401. Other accounts that open
        // the same owner stay signed in.
        DB::table('personal_access_tokens')->where('portal_account_id', $account->id)->delete();
        $account->forceFill(['last_owner_id' => $owner->id, 'last_login_at' => now()])->save();

        return $this->respond($this->signedIn($account, $owner));
    }

    /**
     * The token for acting as the owner through the account, with who the
     * account may switch to. `token` and `owner` keep their old shape.
     *
     * @return array<string, mixed>
     */
    private function signedIn(PortalAccount $account, Owner $owner): array
    {
        $issued = $owner->createToken('mobile');
        $issued->accessToken->forceFill(['portal_account_id' => $account->id])->save();

        return [
            'token' => $issued->plainTextToken,
            'owner' => new OwnerResource($owner),
            'account' => new PortalAccountResource($account),
            'owners' => PortalAccountResource::ownersOf($account),
        ];
    }

    /** Revokes only the token used for this request, leaving other devices signed in. */
    public function logout(): JsonResponse
    {
        $this->owner()->currentAccessToken()->delete();

        return $this->respondMessage(__('api.logged_out'));
    }

    /** Throttles per phone number and IP, so one attacker cannot lock out others. */
    private function throttleKey(string $phone, string $ip): string
    {
        return 'otp-request:'.$this->otp->normalisePhone($phone).'|'.$ip;
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.mobile_otp.max_attempts_per_hour', 5);
    }
}
