<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ParcelBoundary;

/**
 * Reads a parcel's frontage from its surveyed boundary text — which sides
 * face a street and how wide each street is. Corner lots and facing direction
 * drive price in the Saudi market, and the boundary record already states
 * them in words ("شارع عرض 20 متر"); this only parses what is written.
 */
final class ParcelFrontage
{
    public const SIDES = ['n', 'e', 's', 'w'];

    /** Tolerance for calling opposite sides equal (a rectangular lot). */
    private const REGULAR_TOLERANCE = 0.02;

    /**
     * @return array{
     *     streets: array<string, float|null>,
     *     dims: array<string, float|null>,
     *     frontage_count: int,
     *     frontage_length: float,
     *     widest_street: float|null,
     *     is_corner: bool,
     *     is_regular: bool|null,
     *     perimeter: float|null
     * }|null
     */
    public static function read(?ParcelBoundary $boundary): ?array
    {
        if ($boundary === null) {
            return null;
        }

        $streets = [];
        $dims = [];
        foreach (self::SIDES as $side) {
            $text = (string) $boundary->{$side.'_border'};
            $dims[$side] = is_numeric($boundary->{$side.'_dim'}) ? (float) $boundary->{$side.'_dim'} : null;

            if (str_contains($text, 'شارع') || str_contains($text, 'ممر')) {
                // Width is the number in the text, if one was written; a street without one still counts.
                $streets[$side] = preg_match('/(\d+(?:\.\d+)?)/u', self::latinDigits($text), $m) ? (float) $m[1] : null;
            }
        }

        $known = array_filter($dims, static fn (?float $d) => $d !== null);
        $frontageLength = array_sum(array_map(static fn (string $side) => $dims[$side] ?? 0.0, array_keys($streets)));
        $widths = array_filter($streets, static fn (?float $w) => $w !== null);

        return [
            'streets' => $streets,
            'dims' => $dims,
            'frontage_count' => count($streets),
            'frontage_length' => round($frontageLength, 2),
            'widest_street' => $widths === [] ? null : max($widths),
            'is_corner' => count($streets) >= 2,
            'is_regular' => count($known) === 4
                ? self::close($dims['n'], $dims['s']) && self::close($dims['e'], $dims['w'])
                : null,
            'perimeter' => count($known) === 4 ? round(array_sum($known), 2) : null,
        ];
    }

    /**
     * The facing in words, e.g. "شمالية غربية" for a north-west corner lot.
     *
     * @param  array{streets: array<string, float|null>}  $frontage
     */
    public static function facingLabel(array $frontage): ?string
    {
        $sides = array_values(array_intersect(self::SIDES, array_keys($frontage['streets'])));

        return $sides === [] ? null : implode(' ', array_map(static fn (string $s) => __('portal.side.'.$s), $sides));
    }

    private static function close(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) <= max($a, $b) * self::REGULAR_TOLERANCE;
    }

    private static function latinDigits(string $text): string
    {
        return strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }
}
