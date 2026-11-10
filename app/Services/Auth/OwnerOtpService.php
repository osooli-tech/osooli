<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Owner;
use App\Models\PortalAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Issues and verifies the one-time codes used to sign in to the portal and
 * the mobile app.
 *
 * A code is issued to a portal account — the phone that signs in — not to an
 * owner: one phone may open several owners.
 *
 * While no SMS provider is connected, a fixed test code is accepted instead of a
 * generated one. Swapping in real delivery only changes `issue()` — the request
 * and response shapes the app depends on stay the same.
 */
class OwnerOtpService
{
    /** Wrong codes allowed before the issued code is thrown away. */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * The account that may sign in with this phone: active, and opening at
     * least one owner. Pending grants alone do not count.
     */
    public function findAccountByPhone(string $phone): ?PortalAccount
    {
        $account = PortalAccount::findByPhone($phone);

        return $account !== null && $account->usableGrants()->exists() ? $account : null;
    }

    /**
     * Finds an owner by phone number, ignoring formatting differences.
     *
     * This is how sign-in found an owner before portal accounts; it is kept
     * for the backfill, which must reproduce that choice exactly.
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
    public function issue(PortalAccount $account): int
    {
        $ttlMinutes = (int) config('auth.mobile_otp.ttl_minutes', 5);
        $length = self::codeLength();
        $testCode = $this->testCodeFor($account);
        $code = $testCode ?? str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($account), $code, now()->addMinutes($ttlMinutes));
        Cache::put($this->attemptsKey($account), 0, now()->addMinutes($ttlMinutes));

        if ($testCode === null) {
            // TODO: send via SMS provider once credentials are available.
            Log::info('Portal OTP issued', ['portal_account_id' => $account->id]);
        }

        return $ttlMinutes * 60;
    }

    /** How many digits a code has — the portal's code boxes are drawn from it. */
    public static function codeLength(): int
    {
        return (int) config('auth.mobile_otp.code_length', 4);
    }

    /** Verifies a code and consumes it so it cannot be replayed. */
    public function verify(PortalAccount $account, string $code): bool
    {
        $cached = Cache::get($this->cacheKey($account));

        if ($cached === null) {
            return false;
        }

        if (! hash_equals((string) $cached, $code)) {
            // A short code is only safe while guesses are counted: after a few
            // wrong ones the code is burnt and a new one has to be requested.
            if (Cache::increment($this->attemptsKey($account)) >= self::MAX_VERIFY_ATTEMPTS) {
                Cache::forget($this->cacheKey($account));
                Cache::forget($this->attemptsKey($account));
                Log::warning('Portal OTP burnt after repeated wrong codes', ['portal_account_id' => $account->id]);
            }

            return false;
        }

        Cache::forget($this->cacheKey($account));
        Cache::forget($this->attemptsKey($account));

        return true;
    }

    /** Strips formatting and unifies +966 / 00966 / 05… into one comparable form. */
    public function normalisePhone(string $phone): string
    {
        return Owner::normalisePhone($phone);
    }

    private function cacheKey(PortalAccount $account): string
    {
        return "portal_otp_{$account->id}";
    }

    private function attemptsKey(PortalAccount $account): string
    {
        return "portal_otp_attempts_{$account->id}";
    }

    /**
     * The fixed code accepted while SMS is not wired up. In production it is
     * limited to the listed test numbers, so a real owner's number never
     * opens with a code anyone could guess.
     */
    private function testCodeFor(PortalAccount $account): ?string
    {
        $code = config('auth.mobile_otp.test_code');

        if ($code === null || $code === '') {
            return null;
        }

        if (app()->environment('production')) {
            /** @var list<string> $allowed */
            $allowed = config('auth.mobile_otp.test_phones', []);
            $phone = $account->phone_normalized;
            $listed = $phone !== '' && in_array($phone, array_map($this->normalisePhone(...), $allowed), true);

            return $listed ? (string) $code : null;
        }

        return (string) $code;
    }
}
