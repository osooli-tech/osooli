<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;

/**
 * Districts, cities and regions with no boundary, and what to do about each.
 *
 * The National Address boundaries cover the whole kingdom: every region,
 * and cities and villages that tile each region with no gaps. So a record
 * without a boundary is one that came from an import or a migration, not
 * from the National Address:
 *
 *  - a district made from a file's District column. Where its parcels lie
 *    inside its city, its boundary is drawn from them (source 'parcels'):
 *    the hull around its parcels, less the land of neighbouring districts
 *    that have a boundary, inside its city. Where they lie in another city,
 *    it is moved to that city first; where they lie in a district that has
 *    a boundary, it is that district under another name, and its plans move
 *    there.
 *  - a city or a region made by name (بريدة, منطقة القصيم …). Its land is
 *    already a National Address city's or region's, so it is not drawn
 *    again on top of it: its districts (or cities) move to the one they
 *    lie in.
 *
 * Nothing is drawn or moved on a guess: each action needs at least half of
 * the parcels concerned to agree.
 */
final class MissingBoundaries
{
    /** Degrees, about 50 m: the margin around the outermost parcels. */
    private const MARGIN = 0.0005;

    /**
     * @return array{districts: list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}>, cities: list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}>, regions: list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}>}
     */
    public static function report(): array
    {
        return [
            'districts' => self::districts(),
            'cities' => self::cities(),
            'regions' => self::regions(),
        ];
    }

    /**
     * Draws the district's boundary from its parcels, if the report still
     * says so. Returns whether it was drawn.
     */
    public static function drawDistrict(int $districtId): bool
    {
        $row = collect(self::districts($districtId))->first();
        if ($row === null || $row['action'] !== 'draw') {
            return false;
        }

        $shape = self::hull($districtId);
        if ($shape === null) {
            return false;
        }

        // Less the neighbours' land, so the drawn district never overlaps one
        // with a boundary of its own.
        $neighbours = DB::select(
            'SELECT t.id FROM districts t WHERE t.geom IS NOT NULL AND t.id <> ? AND '
            .Spatial::boxesIntersect('t.geom', Spatial::anyFromGeoJson()).' AND ST_Intersects(t.geom, '.Spatial::anyFromGeoJson().')',
            [$districtId, $shape, $shape]
        );
        foreach ($neighbours as $n) {
            $shape = self::polygons(DB::selectOne(
                'SELECT ST_AsGeoJSON(ST_Difference('.Spatial::anyFromGeoJson().', t.geom)) AS g FROM districts t WHERE t.id = ?',
                [$shape, $n->id]
            )?->g);
            if ($shape === null) {
                return false;
            }
        }

        // Inside its city, where the city has a boundary.
        $city = DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')
            ->where('d.id', $districtId)->whereNotNull('c.geom')->value('c.id');
        if ($city !== null) {
            $shape = self::polygons(DB::selectOne(
                'SELECT ST_AsGeoJSON(ST_Intersection('.Spatial::anyFromGeoJson().', c.geom)) AS g FROM cities c WHERE c.id = ?',
                [$shape, $city]
            )?->g);
            if ($shape === null) {
                return false;
            }
        }

        DB::update(
            'UPDATE districts SET geom = '.Spatial::fromGeoJson().", boundary_source = 'parcels', updated_at = ? WHERE id = ? AND geom IS NULL",
            [$shape, now(), $districtId]
        );

        return true;
    }

    /**
     * The row for one record, as the report has it — checked again at the
     * moment of acting, not trusted from when the screen was drawn.
     *
     * @return array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}|null
     */
    public static function row(string $level, int $id): ?array
    {
        $rows = match ($level) {
            'districts' => self::districts($id),
            'cities' => self::cities($id),
            'regions' => self::regions($id),
            default => [],
        };

        return $rows[0] ?? null;
    }

    /** @return list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}> */
    private static function districts(?int $only = null): array
    {
        $rows = DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')
            ->whereNull('d.geom')
            ->when($only !== null, fn ($q) => $q->where('d.id', $only))
            ->orderBy('c.name_ar')->orderBy('d.name_ar')
            ->selectRaw('d.id, d.name_ar, c.id AS city_id, c.name_ar AS city, CASE WHEN c.geom IS NULL THEN 0 ELSE 1 END AS city_bounded')
            ->get();

        $out = [];
        foreach ($rows as $d) {
            $where = 'p.plan_id IN (SELECT id FROM plans WHERE district_id = ?)';
            $total = self::located($where, (int) $d->id);
            $entry = ['id' => (int) $d->id, 'name' => (string) $d->name_ar, 'parent' => (string) $d->city, 'parcels' => $total, 'target_id' => null, 'target' => null];

            if ($total === 0) {
                $out[] = $entry + ['action' => 'no_parcels'];

                continue;
            }

            // Is it a bounded district under another name?
            $inDistrict = self::top('districts', $where, (int) $d->id, (int) $d->id);
            if ($inDistrict !== null && $total <= $inDistrict['parcels'] * 2) {
                $out[] = ['action' => 'merge', 'target_id' => $inDistrict['id'], 'target' => $inDistrict['name']] + $entry;

                continue;
            }

            // Do its parcels lie in its city at all?
            if ((int) $d->city_bounded === 1) {
                $inCity = self::top('cities', $where, (int) $d->id, null, (int) $d->city_id);
                if ($inCity === null || $total > $inCity['parcels'] * 2) {
                    $elsewhere = self::top('cities', $where, (int) $d->id);
                    $out[] = ['action' => $elsewhere !== null && $total <= $elsewhere['parcels'] * 2 ? 'move' : 'scattered', 'target_id' => $elsewhere['id'] ?? null, 'target' => $elsewhere['name'] ?? null] + $entry;

                    continue;
                }
            }

            $out[] = $entry + ['action' => 'draw'];
        }

        return $out;
    }

    /** @return list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}> */
    private static function cities(?int $only = null): array
    {
        $rows = DB::table('cities as c')->leftJoin('regions as r', 'r.id', '=', 'c.region_id')
            ->whereNull('c.geom')
            ->when($only !== null, fn ($q) => $q->where('c.id', $only))
            ->orderBy('r.name_ar')->orderBy('c.name_ar')
            ->get(['c.id', 'c.name_ar', 'r.name_ar as region']);

        return self::merges($rows, 'cities', 'p.plan_id IN (SELECT pl.id FROM plans pl JOIN districts d ON d.id = pl.district_id WHERE d.city_id = ?)');
    }

    /** @return list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}> */
    private static function regions(?int $only = null): array
    {
        $rows = DB::table('regions as r')->leftJoin('countries as n', 'n.id', '=', 'r.country_id')
            ->whereNull('r.geom')
            ->when($only !== null, fn ($q) => $q->where('r.id', $only))
            ->orderBy('r.name_ar')
            ->get(['r.id', 'r.name_ar', 'n.name_ar as region']);

        return self::merges($rows, 'regions', 'p.plan_id IN (SELECT pl.id FROM plans pl JOIN districts d ON d.id = pl.district_id JOIN cities c ON c.id = d.city_id WHERE c.region_id = ?)');
    }

    /**
     * A city or region without a boundary: which bounded one its parcels lie
     * in, for its children to move there.
     *
     * @param  iterable<object>  $rows
     * @return list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null}>
     */
    private static function merges(iterable $rows, string $table, string $where): array
    {
        $out = [];
        foreach ($rows as $r) {
            $total = self::located($where, (int) $r->id);
            $entry = ['id' => (int) $r->id, 'name' => (string) $r->name_ar, 'parent' => (string) ($r->region ?? ''), 'parcels' => $total, 'target_id' => null, 'target' => null];

            if ($total === 0) {
                $out[] = $entry + ['action' => 'no_parcels'];

                continue;
            }

            $into = self::top($table, $where, (int) $r->id, (int) $r->id);
            $out[] = $into !== null && $total <= $into['parcels'] * 2
                ? ['action' => 'merge', 'target_id' => $into['id'], 'target' => $into['name']] + $entry
                : $entry + ['action' => 'scattered'];
        }

        return $out;
    }

    /** Live parcels with a polygon matching the condition. */
    private static function located(string $where, int $id): int
    {
        return (int) DB::selectOne(
            "SELECT COUNT(*) AS n FROM parcels p WHERE {$where} AND p.geom IS NOT NULL AND p.deleted_at IS NULL",
            [$id]
        )?->n;
    }

    /**
     * The bounded record of `$table` holding most of those parcels — or, with
     * `$onlyId`, how many that one record holds.
     *
     * @return array{id: int, name: string, parcels: int}|null
     */
    private static function top(string $table, string $where, int $id, ?int $exceptId = null, ?int $onlyId = null): ?array
    {
        $point = 'ST_PointOnSurface(p.geom)';
        $bindings = [$id];
        $filter = '';
        if ($exceptId !== null) {
            $filter .= ' AND t.id <> ?';
            $bindings[] = $exceptId;
        }
        if ($onlyId !== null) {
            $filter .= ' AND t.id = ?';
            $bindings[] = $onlyId;
        }

        $row = DB::selectOne(
            "SELECT t.id, t.name_ar, COUNT(*) AS n
             FROM parcels p
             JOIN {$table} t ON t.geom IS NOT NULL AND ".Spatial::boxesIntersect('t.geom', $point)." AND ST_Contains(t.geom, {$point})
             WHERE {$where} AND p.geom IS NOT NULL AND p.deleted_at IS NULL{$filter}
             GROUP BY t.id, t.name_ar
             ORDER BY COUNT(*) DESC, t.id
             LIMIT 1",
            $bindings
        );

        return $row === null ? null : ['id' => (int) $row->id, 'name' => (string) $row->name_ar, 'parcels' => (int) $row->n];
    }

    /** The hull around a district's parcels with a margin, as GeoJSON. */
    private static function hull(int $districtId): ?string
    {
        $parts = [];
        $rows = DB::select(
            'SELECT ST_AsGeoJSON(ST_ConvexHull(p.geom)) AS g FROM parcels p
             WHERE p.plan_id IN (SELECT id FROM plans WHERE district_id = ?) AND p.geom IS NOT NULL AND p.deleted_at IS NULL',
            [$districtId]
        );
        foreach ($rows as $r) {
            $geometry = json_decode((string) $r->g, true);
            if (is_array($geometry) && ($geometry['type'] ?? null) === 'Polygon') {
                $parts[] = $geometry['coordinates'];
            }
        }

        if ($parts === []) {
            return null;
        }

        return self::polygons(DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Buffer(ST_ConvexHull('.Spatial::anyFromGeoJson().'), ?)) AS g',
            [json_encode(['type' => 'MultiPolygon', 'coordinates' => $parts]), self::MARGIN]
        )?->g);
    }

    /**
     * The polygons of a GeoJSON geometry as a MultiPolygon document, or null
     * when nothing with an area is left.
     */
    private static function polygons(mixed $json): ?string
    {
        $geometry = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($geometry)) {
            return null;
        }

        $collect = static function (array $g) use (&$collect): array {
            return match ($g['type'] ?? null) {
                'Polygon' => [$g['coordinates']],
                'MultiPolygon' => $g['coordinates'],
                'GeometryCollection' => array_merge([], ...array_map($collect, $g['geometries'] ?? [])),
                default => [],
            };
        };

        $polygons = array_values(array_filter($collect($geometry), static fn ($p): bool => is_array($p) && $p !== []));

        return $polygons === [] ? null : (string) json_encode(['type' => 'MultiPolygon', 'coordinates' => $polygons]);
    }
}
