<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\RequestOtpRequest;
use App\Http\Requests\Api\VerifyOtpRequest;
use App\Http\Resources\OwnerResource;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Http\JsonResponse;
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

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxAttempts())) {
            return $this->respondError(__('api.too_many_attempts'), Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($throttleKey, 3600);

        $owner = $this->otp->findOwnerByPhone($phone);

        if ($owner === null) {
            return $this->respondError(__('api.phone_not_registered'), Response::HTTP_NOT_FOUND);
        }

        return $this->respond([
            'message' => __('api.otp_sent'),
            'expires_in' => $this->otp->issue($owner),
        ]);
    }

    /** Exchanges a valid code for an API token. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $owner = $this->otp->findOwnerByPhone((string) $request->validated('phone'));

        if ($owner === null || ! $this->otp->verify($owner, (string) $request->validated('otp'))) {
            return $this->respondInvalid('otp', __('api.otp_invalid'));
        }

        return $this->respond([
            'token' => $owner->createToken('mobile')->plainTextToken,
            'owner' => new OwnerResource($owner),
        ]);
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
