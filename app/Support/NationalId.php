<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * An owner's national id at rest: the number itself is encrypted, and a keyed
 * fingerprint of it sits beside it. The ciphertext differs every time, so it
 * cannot be compared; the fingerprint is always the same for the same number,
 * which is what the unique index, the exact search and import matching use.
 *
 * Every read or write of owners.national_id that bypasses the Owner model
 * (raw import and archive queries) must go through this class.
 */
final class NationalId
{
    /** Separates this key from every other use of the application key. */
    private const KEY_CONTEXT = 'owners.national_id.fingerprint';

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** One spelling per number: trimmed, no inner spaces, Latin digits. */
    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $value = str_replace(self::ARABIC_DIGITS, $latin, $value);
        $value = str_replace(self::PERSIAN_DIGITS, $latin, $value);
        $value = (string) preg_replace('/\s+/u', '', $value);

        return $value === '' ? null : $value;
    }

    /** The fingerprint stored in owners.national_id_hash; null for no number. */
    public static function hash(?string $value): ?string
    {
        $value = self::normalise($value);

        return $value === null ? null : hash_hmac('sha256', $value, self::key());
    }

    /**
     * The fingerprint to search by. Never null: `where(col, null)` would turn
     * into IS NULL and match every owner without a number.
     */
    public static function lookup(?string $value): string
    {
        return self::hash($value) ?? '';
    }

    public static function encrypt(?string $value): ?string
    {
        $value = self::normalise($value);

        return $value === null ? null : Crypt::encryptString($value);
    }

    /** A value that is not ciphertext is returned as it is, so rows not yet migrated still read. */
    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }

    /**
     * The two columns for a raw insert or update.
     *
     * @return array{national_id: string|null, national_id_hash: string|null}
     */
    public static function columns(?string $value): array
    {
        return ['national_id' => self::encrypt($value), 'national_id_hash' => self::hash($value)];
    }

    private static function key(): string
    {
        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? (string) base64_decode(substr($appKey, 7), true) : $appKey;

        return hash_hkdf('sha256', $raw, 32, self::KEY_CONTEXT);
    }
}
