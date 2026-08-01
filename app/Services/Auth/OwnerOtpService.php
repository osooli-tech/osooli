<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Owner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Issues and verifies the one-time codes owners use to sign in to the mobile app.
 *
 * While no SMS provider is connected, a fixed test code is accepted instead of a
 * generated one. Swapping in real delivery only changes `issue()` — the request
 * and response shapes the app depends on stay the same.
 */
class OwnerOtpService
{
    /**
     * Finds an owner by phone number, ignoring formatting differences.
     *
     * Stored numbers vary (05…, +9665…, 9665…), so both sides are reduced to
     * the same national form before comparing.
     */
    public function findOwnerByPhone(string $phone): ?Owner
    {
        $normalised = $this->normalisePhone($phone);

        if ($normalised === '') {
            return null;
        }

        return Owner::query()
            ->whereNotNull('phone')
            // Strip non-digits, then drop a 966 / 00966 country code and any
            // leading zeros — mirroring normalisePhone() in SQL.
            ->whereRaw(
                "ltrim(regexp_replace(regexp_replace(phone, '[^0-9]', '', 'g'), '^(00966|966)', ''), '0') = ?",
                [$normalised]
            )
            ->first();
    }

    /**
     * Issues a code for the owner and returns how long it stays valid.
     *
     * @return int seconds until expiry
     */
    public function issue(Owner $owner): int
    {
        $ttlMinutes = (int) config('auth.mobile_otp.ttl_minutes', 5);
        $code = $this->testCode() ?? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($owner), $code, now()->addMinutes($ttlMinutes));

        if ($this->testCode() === null) {
            // TODO: send via SMS provider once credentials are available.
            Log::info('Owner OTP issued', ['owner_id' => $owner->id]);
        }

        return $ttlMinutes * 60;
    }

    /** Verifies a code and consumes it so it cannot be replayed. */
    public function verify(Owner $owner, string $code): bool
    {
        $cached = Cache::get($this->cacheKey($owner));

        if ($cached === null || ! hash_equals((string) $cached, $code)) {
            return false;
        }

        Cache::forget($this->cacheKey($owner));

        return true;
    }

    /** Strips formatting and unifies +966 / 00966 / 05… into one comparable form. */
    public function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        foreach (['00966', '966'] as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                $digits = substr($digits, strlen($prefix));
                break;
            }
        }

        return ltrim($digits, '0');
    }

    private function cacheKey(Owner $owner): string
    {
        return "owner_otp_{$owner->id}";
    }

    /** The fixed code accepted while SMS is not wired up — never in production. */
    private function testCode(): ?string
    {
        if (app()->environment('production')) {
            return null;
        }

        $code = config('auth.mobile_otp.test_code');

        return $code === null || $code === '' ? null : (string) $code;
    }
}
