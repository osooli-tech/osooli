<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Measurements of a GeoJSON MultiPolygon, computed in PHP.
 *
 * PostGIS answers these questions itself. MariaDB cannot answer all of them —
 * it has no geography type and no ST_IsValidReason — so when MariaDB is the
 * primary database the polygon checks run here instead. The rules mirror
 * what PostGIS enforces for the cases a person drawing a parcel actually
 * produces: an unclosed or too-short ring, and an edge crossing another.
 *
 * Coordinates are [lng, lat] in degrees (WGS 84), as GeoJSON requires.
 */
final class GeometryMath
{
    /**
     * @param  list<list<list<array{0: float|int, 1: float|int}>>>  $multiPolygon  coordinates of a MultiPolygon
     * @return array{valid: bool, reason: string|null, area: float, points: int, bbox: array{0: float, 1: float, 2: float, 3: float}}
     */
    public static function measure(array $multiPolygon): array
    {
        $points = 0;
        $bbox = [INF, INF, -INF, -INF];

        foreach ($multiPolygon as $polygon) {
            foreach ($polygon as $ring) {
                foreach ($ring as $position) {
                    $points++;
                    $bbox = [
                        min($bbox[0], (float) $position[0]), min($bbox[1], (float) $position[1]),
                        max($bbox[2], (float) $position[0]), max($bbox[3], (float) $position[1]),
                    ];
                }
            }
        }

        return [
            'valid' => ($reason = self::invalidReason($multiPolygon)) === null,
            'reason' => $reason,
            'area' => $points === 0 ? 0.0 : self::areaSqm($multiPolygon, ($bbox[1] + $bbox[3]) / 2),
            'points' => $points,
            'bbox' => $bbox,
        ];
    }

    /**
     * Area in square metres: the planar area in square degrees scaled by the
     * length of a degree at `$latitude` — the same series Spatial::areaSqm()
     * uses on MariaDB, so both report the same figure.
     *
     * @param  list<list<list<array{0: float|int, 1: float|int}>>>  $multiPolygon
     */
    public static function areaSqm(array $multiPolygon, float $latitude): float
    {
        $squareDegrees = 0.0;

        foreach ($multiPolygon as $polygon) {
            foreach ($polygon as $index => $ring) {
                // The first ring is the shell; any after it are holes.
                $ringArea = abs(self::shoelace($ring));
                $squareDegrees += $index === 0 ? $ringArea : -$ringArea;
            }
        }

        $phi = deg2rad($latitude);
        $metresPerDegreeLat = 111132.954 - 559.822 * cos(2 * $phi) + 1.175 * cos(4 * $phi);
        $metresPerDegreeLng = 111412.84 * cos($phi) - 93.5 * cos(3 * $phi);

        return abs($squareDegrees) * $metresPerDegreeLat * $metresPerDegreeLng;
    }

    /**
     * A WGS84 lng/lat position projected to UTM zone 38N (EPSG:32638) — the
     * zone covering every parcel on record, matching the easting/northing a
     * licensed surveyor's plan states. PostGIS does this with ST_Transform;
     * MariaDB has no equivalent, so the print report's corner table falls
     * back to this classic Snyder transverse-Mercator series, accurate to
     * sub-centimetre for a single UTM zone's span.
     *
     * @return array{easting: float, northing: float}
     */
    public static function toUtmZone38N(float $lng, float $lat): array
    {
        // WGS84 ellipsoid.
        $a = 6378137.0;
        $f = 1 / 298.257223563;
        $k0 = 0.9996;
        $e2 = $f * (2 - $f);
        $ep2 = $e2 / (1 - $e2);

        // Zone 38's central meridian: zone * 6 - 183.
        $phi = deg2rad($lat);
        $lambda = deg2rad($lng);
        $lambda0 = deg2rad(45.0);

        $sinPhi = sin($phi);
        $cosPhi = cos($phi);
        $tanPhi = tan($phi);

        $n = $a / sqrt(1 - $e2 * $sinPhi ** 2);
        $t = $tanPhi ** 2;
        $c = $ep2 * $cosPhi ** 2;
        $bigA = $cosPhi * ($lambda - $lambda0);

        $m = $a * (
            (1 - $e2 / 4 - 3 * $e2 ** 2 / 64 - 5 * $e2 ** 3 / 256) * $phi
            - (3 * $e2 / 8 + 3 * $e2 ** 2 / 32 + 45 * $e2 ** 3 / 1024) * sin(2 * $phi)
            + (15 * $e2 ** 2 / 256 + 45 * $e2 ** 3 / 1024) * sin(4 * $phi)
            - (35 * $e2 ** 3 / 3072) * sin(6 * $phi)
        );

        $easting = $k0 * $n * (
            $bigA
            + (1 - $t + $c) * $bigA ** 3 / 6
            + (5 - 18 * $t + $t ** 2 + 72 * $c - 58 * $ep2) * $bigA ** 5 / 120
        ) + 500000.0;

        $northing = $k0 * (
            $m + $n * $tanPhi * (
                $bigA ** 2 / 2
                + (5 - $t + 9 * $c + 4 * $c ** 2) * $bigA ** 4 / 24
                + (61 - 58 * $t + $t ** 2 + 600 * $c - 330 * $ep2) * $bigA ** 6 / 720
            )
        );

        return ['easting' => $easting, 'northing' => $northing];
    }

    /**
     * Why the polygon is invalid, worded as PostGIS words it, or null.
     *
     * @param  list<list<list<array{0: float|int, 1: float|int}>>>  $multiPolygon
     */
    public static function invalidReason(array $multiPolygon): ?string
    {
        foreach ($multiPolygon as $polygon) {
            $segments = [];

            foreach ($polygon as $ringIndex => $ring) {
                // A doubled vertex is a zero-length edge, not a crossing.
                $ring = array_values(array_filter(
                    $ring,
                    static fn (array $p, int $i): bool => $i === 0 || $p[0] != $ring[$i - 1][0] || $p[1] != $ring[$i - 1][1],
                    ARRAY_FILTER_USE_BOTH
                ));
                $count = count($ring);

                if ($count < 4) {
                    return 'Too few points in geometry component';
                }

                if ($ring[0][0] != $ring[$count - 1][0] || $ring[0][1] != $ring[$count - 1][1]) {
                    return 'Ring is not closed';
                }

                for ($i = 0; $i < $count - 1; $i++) {
                    $segments[] = [$ringIndex, $i, $count - 1, $ring[$i], $ring[$i + 1]];
                }
            }

            $total = count($segments);

            for ($a = 0; $a < $total; $a++) {
                for ($b = $a + 1; $b < $total; $b++) {
                    [$ringA, $iA, $lenA, $p1, $p2] = $segments[$a];
                    [$ringB, $iB, $lenB, $q1, $q2] = $segments[$b];

                    // Consecutive edges of one ring share a vertex by design.
                    $adjacent = $ringA === $ringB
                        && (abs($iA - $iB) === 1 || abs($iA - $iB) === $lenA - 1);

                    if (! $adjacent && self::segmentsIntersect($p1, $p2, $q1, $q2)) {
                        return $ringA === $ringB ? 'Self-intersection' : 'Ring Self-intersection';
                    }
                }
            }
        }

        return null;
    }

    /** @param list<array{0: float|int, 1: float|int}> $ring */
    private static function shoelace(array $ring): float
    {
        $sum = 0.0;
        $count = count($ring);

        for ($i = 0; $i < $count - 1; $i++) {
            $sum += (float) $ring[$i][0] * (float) $ring[$i + 1][1] - (float) $ring[$i + 1][0] * (float) $ring[$i][1];
        }

        return $sum / 2;
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $p1
     * @param  array{0: float|int, 1: float|int}  $p2
     * @param  array{0: float|int, 1: float|int}  $q1
     * @param  array{0: float|int, 1: float|int}  $q2
     */
    private static function segmentsIntersect(array $p1, array $p2, array $q1, array $q2): bool
    {
        $d1 = self::orientation($q1, $q2, $p1);
        $d2 = self::orientation($q1, $q2, $p2);
        $d3 = self::orientation($p1, $p2, $q1);
        $d4 = self::orientation($p1, $p2, $q2);

        if ((($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0)) && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0))) {
            return true;
        }

        return ($d1 == 0 && self::onSegment($q1, $q2, $p1))
            || ($d2 == 0 && self::onSegment($q1, $q2, $p2))
            || ($d3 == 0 && self::onSegment($p1, $p2, $q1))
            || ($d4 == 0 && self::onSegment($p1, $p2, $q2));
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $a
     * @param  array{0: float|int, 1: float|int}  $b
     * @param  array{0: float|int, 1: float|int}  $c
     */
    private static function orientation(array $a, array $b, array $c): float
    {
        return ((float) $b[0] - (float) $a[0]) * ((float) $c[1] - (float) $a[1])
            - ((float) $b[1] - (float) $a[1]) * ((float) $c[0] - (float) $a[0]);
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $a
     * @param  array{0: float|int, 1: float|int}  $b
     * @param  array{0: float|int, 1: float|int}  $point  known to be collinear with a–b
     */
    private static function onSegment(array $a, array $b, array $point): bool
    {
        return min($a[0], $b[0]) <= $point[0] && $point[0] <= max($a[0], $b[0])
            && min($a[1], $b[1]) <= $point[1] && $point[1] <= max($a[1], $b[1]);
    }
}
