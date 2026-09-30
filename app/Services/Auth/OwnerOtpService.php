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

        // phone_normalized is maintained by Owner::saving and indexed —
        // the previous double-regexp whereRaw scanned the whole table.
        return Owner::query()
            ->where('phone_normalized', $normalised)
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
        $length = self::codeLength();
        $testCode = $this->testCodeFor($owner);
        $code = $testCode ?? str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($owner), $code, now()->addMinutes($ttlMinutes));

        if ($testCode === null) {
            // TODO: send via SMS provider once credentials are available.
            Log::info('Owner OTP issued', ['owner_id' => $owner->id]);
        }

        return $ttlMinutes * 60;
    }

    /** How many digits a code has — the portal's code boxes are drawn from it. */
    public static function codeLength(): int
    {
        return (int) config('auth.mobile_otp.code_length', 4);
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
        return Owner::normalisePhone($phone);
    }

    private function cacheKey(Owner $owner): string
    {
        return "owner_otp_{$owner->id}";
    }

    /**
     * The fixed code accepted while SMS is not wired up. In production it is
     * limited to the listed test numbers, so a real owner's number never
     * opens with a code anyone could guess.
     */
    private function testCodeFor(Owner $owner): ?string
    {
        $code = config('auth.mobile_otp.test_code');

        if ($code === null || $code === '') {
            return null;
        }

        if (app()->environment('production')) {
            /** @var list<string> $allowed */
            $allowed = config('auth.mobile_otp.test_phones', []);
            $phone = $this->normalisePhone((string) $owner->phone);
            $listed = $phone !== '' && in_array($phone, array_map($this->normalisePhone(...), $allowed), true);

            return $listed ? (string) $code : null;
        }

        return (string) $code;
    }
}
