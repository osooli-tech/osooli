<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A simplified outline of Saudi Arabia (borders and coast, ~70 points) and
 * its main cities, projected to SVG units. Decoration only — the portal's
 * sidebar and sign-in panel draw it; nothing is measured from it.
 */
final class SaudiOutline
{
    private const SCALE = 10;

    private const WEST = 34.4;

    private const EAST = 55.9;

    private const NORTH = 32.4;

    private const SOUTH = 16.1;

    /** Mid-latitude of the country, so degrees of longitude keep their true width. */
    private const MID_LAT = 24.0;

    /** @var list<array{float, float}> [lng, lat], clockwise from the Gulf of Aqaba */
    private const BORDER = [
        [34.95, 29.36], [36.07, 29.19], [36.50, 29.50], [36.75, 29.87], [37.50, 30.00], [37.67, 30.34],
        [37.99, 30.51], [37.00, 31.51], [39.20, 32.16], [40.40, 31.95], [41.44, 31.37], [42.08, 31.10],
        [44.70, 29.18], [46.55, 29.10], [47.46, 29.00], [47.70, 28.53], [48.42, 28.55], [48.80, 27.69],
        [49.30, 27.46], [49.47, 27.11], [50.15, 26.69], [50.21, 26.28], [50.11, 25.94], [50.24, 25.61],
        [50.53, 25.33], [50.66, 24.99], [50.81, 24.75], [51.39, 24.63], [51.58, 24.25], [52.00, 23.00],
        [55.00, 22.50], [55.67, 22.00], [55.00, 20.00], [52.00, 19.00], [49.10, 18.60], [48.20, 18.17],
        [47.47, 17.12], [47.00, 16.95], [46.75, 17.28], [46.37, 17.23], [45.40, 17.33], [45.22, 17.43],
        [44.06, 17.41], [43.79, 17.32], [43.38, 17.58], [43.12, 17.09], [43.22, 16.67], [42.78, 16.35],
        [42.65, 16.77], [42.35, 17.08], [42.27, 17.47], [41.75, 17.83], [41.22, 18.67], [40.94, 19.49],
        [40.25, 20.17], [39.80, 20.34], [39.14, 21.29], [39.02, 21.99], [39.07, 22.58], [38.49, 23.69],
        [38.02, 24.08], [37.48, 24.29], [37.15, 24.86], [37.21, 25.08], [36.93, 25.60], [36.64, 25.83],
        [36.25, 26.57], [35.64, 27.38], [35.13, 28.06], [34.63, 28.06], [34.79, 28.61], [34.83, 28.96],
    ];

    /** @var array<string, array{float, float}> */
    private const CITIES = [
        'riyadh' => [46.72, 24.69], 'jeddah' => [39.17, 21.54], 'makkah' => [39.83, 21.42],
        'madinah' => [39.61, 24.47], 'dammam' => [50.10, 26.43], 'abha' => [42.50, 18.22],
        'tabuk' => [36.57, 28.38], 'hail' => [41.69, 27.52], 'buraydah' => [43.97, 26.33],
        'jazan' => [42.55, 16.89], 'najran' => [44.13, 17.49], 'sakaka' => [40.20, 29.97],
    ];

    /** @return array{0: float, 1: float} the SVG viewBox width and height */
    public static function size(): array
    {
        return self::project(self::EAST, self::SOUTH);
    }

    public static function path(): string
    {
        $points = array_map(fn (array $p): string => implode(' ', self::project($p[0], $p[1])), self::BORDER);

        return 'M'.implode(' L', $points).' Z';
    }

    /** @return array<string, array{0: float, 1: float}> */
    public static function cities(): array
    {
        return array_map(fn (array $p): array => self::project($p[0], $p[1]), self::CITIES);
    }

    /** @return array{0: float, 1: float} */
    private static function project(float $lng, float $lat): array
    {
        $x = ($lng - self::WEST) * cos(deg2rad(self::MID_LAT)) * self::SCALE;
        $y = (self::NORTH - $lat) * self::SCALE;

        return [round($x, 1), round($y, 1)];
    }
}
