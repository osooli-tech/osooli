<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A parcel's outline fitted into a box of an SVG, north up, with each edge
 * named by the side it faces (n/e/s/w) — so the frontage diagram can draw the
 * real shape and put each side's street and length on the edges facing that
 * way, instead of on a square that only fits rectangular lots.
 */
final class LotOutline
{
    /** Vertices turning less than this are dropped, so survey noise does not split a side. */
    private const STRAIGHT_DEGREES = 12.0;

    /** Sides in the order they come round a ring drawn clockwise on screen. */
    private const CLOCKWISE = ['n', 'e', 's', 'w'];

    /** A renamed fit must cut the length mismatch at least this much to be preferred. */
    private const RENAME_GAIN = 0.5;

    /** How much turning a name away from the way its edges face costs, against length mismatch. */
    private const TURN_WEIGHT = 0.05;

    /** Metres per degree of latitude; longitude is scaled by cos(latitude). */
    private const METRES_PER_DEGREE = 111_320.0;

    /**
     * Sides are first named by the way each edge faces; when the deed's side
     * lengths fit the edges better under a quarter-turn renaming (a lot set
     * at 45° whose "north" in the deed faces north-east), the deed wins.
     *
     * @param  array<string, float|null>  $dims  the deed's length per side, when known
     * @return array{
     *     points: list<array{0: float, 1: float}>,
     *     edges: list<array{x1: float, y1: float, x2: float, y2: float, side: string, nx: float, ny: float, length: float}>,
     *     anchors: array<string, array{x1: float, y1: float, x2: float, y2: float, x: float, y: float, nx: float, ny: float, angle: float}>
     * }
     */
    public static function fit(?string $geojson, float $x, float $y, float $width, float $height, array $dims = []): array
    {
        $ring = self::ring($geojson) ?? [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]];
        [$points, $scale] = self::place(self::straighten($ring), $x, $y, $width, $height);

        $area = 0.0;
        $count = count($points);
        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[($i + 1) % $count];
            $area += $x1 * $y2 - $x2 * $y1;
        }

        $edges = [];
        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[($i + 1) % $count];
            $length = hypot($x2 - $x1, $y2 - $y1);
            if ($length < 1e-6) {
                continue;
            }
            // Screen y grows downwards, so a positive area is a clockwise ring.
            [$nx, $ny] = $area > 0
                ? [($y2 - $y1) / $length, -($x2 - $x1) / $length]
                : [-($y2 - $y1) / $length, ($x2 - $x1) / $length];

            $side = abs($ny) >= abs($nx) ? ($ny < 0 ? 'n' : 's') : ($nx > 0 ? 'e' : 'w');
            $edges[] = compact('x1', 'y1', 'x2', 'y2', 'side', 'nx', 'ny', 'length');
        }

        $edges = self::renameToDeed($edges, $dims, $scale);

        return ['points' => $points, 'edges' => $edges, 'anchors' => self::anchors($edges)];
    }

    /**
     * @param  list<array{x1: float, y1: float, x2: float, y2: float, side: string, nx: float, ny: float, length: float}>  $edges
     * @param  array<string, float|null>  $dims
     * @return list<array{x1: float, y1: float, x2: float, y2: float, side: string, nx: float, ny: float, length: float}>
     */
    private static function renameToDeed(array $edges, array $dims, float $scale): array
    {
        $metres = [];
        $facing = [];
        foreach ($edges as $edge) {
            $metres[$edge['side']] = ($metres[$edge['side']] ?? 0.0) + $edge['length'] / $scale;
            $facing[$edge['side']][0] = ($facing[$edge['side']][0] ?? 0.0) + $edge['nx'] * $edge['length'];
            $facing[$edge['side']][1] = ($facing[$edge['side']][1] ?? 0.0) + $edge['ny'] * $edge['length'];
        }

        $mismatch = static function (int $turn) use ($metres, $dims): ?float {
            $total = null;
            foreach ($metres as $side => $length) {
                $named = self::CLOCKWISE[(array_search($side, self::CLOCKWISE, true) + $turn) % 4];
                if (($dims[$named] ?? null) > 0) {
                    $total = ($total ?? 0.0) + abs($length - $dims[$named]) / $dims[$named];
                }
            }

            return $total;
        };

        // Lengths alone cannot tell a quarter turn one way from the other on a
        // lot whose opposite sides match, so the smaller turn away from the
        // way the edges actually face settles it.
        $turning = static function (int $turn) use ($facing): float {
            $towards = ['n' => -90.0, 'e' => 0.0, 's' => 90.0, 'w' => 180.0];
            $total = 0.0;
            foreach ($facing as $side => [$nx, $ny]) {
                $named = self::CLOCKWISE[(array_search($side, self::CLOCKWISE, true) + $turn) % 4];
                $gap = abs(rad2deg(atan2($ny, $nx)) - $towards[$named]);
                $total += min($gap, 360 - $gap) / 180;
            }

            return $total / max(count($facing), 1);
        };

        $best = 0;
        $baseCost = $mismatch(0);
        $bestCost = null;
        foreach ([1, 3, 2] as $turn) {
            $cost = $mismatch($turn);
            if ($cost === null || $baseCost === null || $cost >= $baseCost * self::RENAME_GAIN) {
                continue;
            }
            $cost += self::TURN_WEIGHT * $turning($turn);
            if ($bestCost === null || $cost < $bestCost) {
                [$best, $bestCost] = [$turn, $cost];
            }
        }

        if ($best === 0) {
            return $edges;
        }

        return array_map(static function (array $edge) use ($best): array {
            $edge['side'] = self::CLOCKWISE[(array_search($edge['side'], self::CLOCKWISE, true) + $best) % 4];

            return $edge;
        }, $edges);
    }

    /**
     * Each side as one straight line along the outside of its edges — the
     * place for its street and its length, even when the side is ragged.
     *
     * @param  list<array{x1: float, y1: float, x2: float, y2: float, side: string, nx: float, ny: float, length: float}>  $edges
     * @return array<string, array{x1: float, y1: float, x2: float, y2: float, x: float, y: float, nx: float, ny: float, angle: float}>
     */
    private static function anchors(array $edges): array
    {
        $anchors = [];
        foreach (collect($edges)->groupBy('side') as $side => $group) {
            $nx = $group->sum(fn (array $e): float => $e['nx'] * $e['length']);
            $ny = $group->sum(fn (array $e): float => $e['ny'] * $e['length']);
            $norm = max(hypot($nx, $ny), 1e-9);
            [$nx, $ny] = [$nx / $norm, $ny / $norm];
            [$dx, $dy] = [-$ny, $nx];

            $along = $group->flatMap(fn (array $e): array => [$e['x1'] * $dx + $e['y1'] * $dy, $e['x2'] * $dx + $e['y2'] * $dy]);
            $out = $group->flatMap(fn (array $e): array => [$e['x1'] * $nx + $e['y1'] * $ny, $e['x2'] * $nx + $e['y2'] * $ny])->max();
            [$from, $to] = [$along->min(), $along->max()];

            $angle = rad2deg(atan2($dy, $dx));
            // Keep the text upright: turn anything past vertical round by half a turn.
            if ($angle > 90) {
                $angle -= 180;
            } elseif ($angle <= -90) {
                $angle += 180;
            }

            $anchors[(string) $side] = [
                'x1' => round($dx * $from + $nx * $out, 2),
                'y1' => round($dy * $from + $ny * $out, 2),
                'x2' => round($dx * $to + $nx * $out, 2),
                'y2' => round($dy * $to + $ny * $out, 2),
                'x' => round($dx * ($from + $to) / 2 + $nx * $out, 2),
                'y' => round($dy * ($from + $to) / 2 + $ny * $out, 2),
                'nx' => round($nx, 4),
                'ny' => round($ny, 4),
                'angle' => round($angle, 2),
            ];
        }

        return $anchors;
    }

    /** @return list<array{0: float, 1: float}>|null outer ring in metres, east and north positive */
    private static function ring(?string $geojson): ?array
    {
        $geometry = $geojson === null ? null : json_decode($geojson, true);
        $outer = match (is_array($geometry) ? ($geometry['type'] ?? null) : null) {
            'MultiPolygon' => $geometry['coordinates'][0][0] ?? null,
            'Polygon' => $geometry['coordinates'][0] ?? null,
            default => null,
        };

        if (! is_array($outer) || count($outer) < 4) {
            return null;
        }

        if ($outer[0] == $outer[count($outer) - 1]) {
            array_pop($outer);
        }

        $lat = deg2rad(array_sum(array_column($outer, 1)) / count($outer));

        return array_map(static fn (array $p): array => [
            (float) $p[0] * self::METRES_PER_DEGREE * cos($lat),
            (float) $p[1] * self::METRES_PER_DEGREE,
        ], array_values($outer));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function straighten(array $ring): array
    {
        do {
            $removed = false;
            $count = count($ring);
            for ($i = 0; $count > 3 && $i < $count; $i++) {
                [$ax, $ay] = $ring[($i - 1 + $count) % $count];
                [$bx, $by] = $ring[$i];
                [$cx, $cy] = $ring[($i + 1) % $count];
                $turn = abs(rad2deg(atan2($cy - $by, $cx - $bx) - atan2($by - $ay, $bx - $ax)));
                $turn = min($turn, 360 - $turn);
                if ($turn < self::STRAIGHT_DEGREES || hypot($cx - $bx, $cy - $by) < 1e-9) {
                    array_splice($ring, $i, 1);
                    $removed = true;
                    break;
                }
            }
        } while ($removed);

        return $ring;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return array{0: list<array{0: float, 1: float}>, 1: float} the points and metres-to-box scale
     */
    private static function place(array $ring, float $x, float $y, float $width, float $height): array
    {
        $xs = array_column($ring, 0);
        $ys = array_column($ring, 1);
        $spanX = max(max($xs) - min($xs), 1e-9);
        $spanY = max(max($ys) - min($ys), 1e-9);
        $scale = min($width / $spanX, $height / $spanY);
        $offsetX = $x + ($width - $spanX * $scale) / 2;
        $offsetY = $y + ($height - $spanY * $scale) / 2;
        $minX = min($xs);
        $maxY = max($ys);

        return [array_map(static fn (array $p): array => [
            round($offsetX + ($p[0] - $minX) * $scale, 2),
            round($offsetY + ($maxY - $p[1]) * $scale, 2),
        ], $ring), $scale];
    }
}
