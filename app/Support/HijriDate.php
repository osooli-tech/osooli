<?php

declare(strict_types=1);

namespace App\Support;

use IntlDateFormatter;

/**
 * Deed dates are stored as Hijri text ("1443-11-14"), never as a Gregorian
 * date. This converts them through ICU's Umm al-Qura calendar — the official
 * Saudi one — instead of any hand-rolled arithmetic.
 */
final class HijriDate
{
    private const TIMEZONE = 'Asia/Riyadh';

    /** Unix timestamp of a stored Hijri date, or null if it cannot be read. */
    public static function toTimestamp(?string $hijri): ?int
    {
        if ($hijri === null || ! extension_loaded('intl') || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $hijri)) {
            return null;
        }

        $parsed = self::formatter('yyyy-MM-dd')->parse(substr($hijri, 0, 10));

        return $parsed === false ? null : (int) $parsed;
    }

    public static function currentYear(): ?int
    {
        return extension_loaded('intl') ? (int) self::formatter('yyyy')->format(time()) : null;
    }

    /** Whole Hijri years since the date — how old a deed is. */
    public static function ageInYears(?string $hijri): ?int
    {
        $current = self::currentYear();

        return $current !== null && $hijri !== null && preg_match('/^(\d{4})/', $hijri, $m)
            ? max(0, $current - (int) $m[1])
            : null;
    }

    private static function formatter(string $pattern): IntlDateFormatter
    {
        return new IntlDateFormatter(
            'en@calendar=islamic-umalqura',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );
    }
}
