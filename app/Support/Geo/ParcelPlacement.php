<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;

/**
 * Whether a parcel lies where its plan says: inside the plan's district,
 * and so inside that district's city and region.
 *
 * The parcel is represented by a point on its surface — inside the polygon
 * however oddly shaped — and tested against the boundaries loaded from the
 * National Address. The answer is only ever advice: a boundary can be out
 * of date or approximate, and a parcel on a district's edge is common, so
 * callers show a warning and never refuse the save.
 *
 * The finest boundary available decides: the district's if it has one,
 * otherwise the city's, otherwise the region's. When the parcel is outside
 * it, the boundaries it actually falls in are named as the suggestion.
 */
final class ParcelPlacement
{
    /**
     * @return array{status: string, level: string|null, expected: string|null, approximate: bool, actual: array{district: string|null, city: string|null, region: string|null}}
     *   status: ok, outside, no_boundary (nothing to check against), no_geometry
     */
    public static function forParcel(int $parcelId, ?int $districtId = null): array
    {
        $row = DB::selectOne(
            'SELECT ST_X(ST_PointOnSurface(p.geom)) AS x, ST_Y(ST_PointOnSurface(p.geom)) AS y, pl.district_id
             FROM parcels p LEFT JOIN plans pl ON pl.id = p.plan_id WHERE p.id = ?',
            [$parcelId]
        );

        if ($row === null || $row->x === null) {
            return self::result('no_geometry');
        }

        return self::check((float) $row->x, (float) $row->y, $districtId ?? ($row->district_id === null ? null : (int) $row->district_id));
    }

    /**
     * The same check for a polygon not saved yet (a GeoJSON MultiPolygon).
     *
     * @return array{status: string, level: string|null, expected: string|null, approximate: bool, actual: array{district: string|null, city: string|null, region: string|null}}
     */
    public static function forGeometry(string $geojson, ?int $districtId): array
    {
        $row = DB::selectOne(
            'SELECT ST_X(ST_PointOnSurface(g)) AS x, ST_Y(ST_PointOnSurface(g)) AS y FROM (SELECT '.Spatial::fromGeoJson().' AS g) s',
            [Spatial::multiPolygonJson($geojson)]
        );

        if ($row === null || $row->x === null) {
            return self::result('no_geometry');
        }

        return self::check((float) $row->x, (float) $row->y, $districtId);
    }

    /**
     * @return array{status: string, level: string|null, expected: string|null, approximate: bool, actual: array{district: string|null, city: string|null, region: string|null}}
     */
    public static function check(float $lng, float $lat, ?int $districtId): array
    {
        $actual = [
            'district' => self::containing('districts', $lng, $lat),
            'city' => self::containing('cities', $lng, $lat),
            'region' => self::containing('regions', $lng, $lat),
        ];

        // What the plan claims, from the finest level that has a boundary.
        $expected = null;
        if ($districtId !== null) {
            $district = DB::table('districts')->where('id', $districtId)->first(['id', 'name_ar', 'city_id', 'boundary_source']);
            $city = $district ? DB::table('cities')->where('id', $district->city_id)->first(['id', 'name_ar', 'region_id', 'boundary_source']) : null;
            $region = $city ? DB::table('regions')->where('id', $city->region_id)->first(['id', 'name_ar', 'boundary_source']) : null;

            foreach (['district' => $district, 'city' => $city, 'region' => $region] as $level => $record) {
                if ($record !== null && $record->boundary_source !== null) {
                    $expected = ['level' => $level, 'id' => (int) $record->id, 'name' => (string) $record->name_ar, 'source' => (string) $record->boundary_source];
                    break;
                }
            }
        }

        if ($expected === null) {
            return self::result('no_boundary', actual: self::names($actual));
        }

        $inside = ($actual[$expected['level']]['ids'] ?? []) !== [] && in_array($expected['id'], $actual[$expected['level']]['ids'], true);

        return self::result(
            $inside ? 'ok' : 'outside',
            $expected['level'],
            $expected['name'],
            $expected['source'] === 'approximate',
            self::names($actual)
        );
    }

    /**
     * The records of `$table` whose boundary contains the point.
     *
     * @return array{ids: list<int>, name: string|null}
     */
    private static function containing(string $table, float $lng, float $lat): array
    {
        $point = Spatial::point();
        $rows = DB::select(
            "SELECT id, name_ar FROM {$table}
             WHERE geom IS NOT NULL AND ".Spatial::boxesIntersect('geom', $point)." AND ST_Contains(geom, {$point})
             ORDER BY CASE boundary_source WHEN 'manual' THEN 0 WHEN 'official' THEN 1 WHEN 'derived' THEN 2 ELSE 3 END",
            [$lng, $lat, $lng, $lat]
        );

        return [
            'ids' => array_map(static fn (object $r): int => (int) $r->id, $rows),
            'name' => $rows === [] ? null : (string) $rows[0]->name_ar,
        ];
    }

    /**
     * @param  array<string, array{ids: list<int>, name: string|null}>  $actual
     * @return array{district: string|null, city: string|null, region: string|null}
     */
    private static function names(array $actual): array
    {
        return [
            'district' => $actual['district']['name'],
            'city' => $actual['city']['name'],
            'region' => $actual['region']['name'],
        ];
    }

    /**
     * @param  array{district: string|null, city: string|null, region: string|null}  $actual
     * @return array{status: string, level: string|null, expected: string|null, approximate: bool, actual: array{district: string|null, city: string|null, region: string|null}}
     */
    private static function result(string $status, ?string $level = null, ?string $expected = null, bool $approximate = false, array $actual = ['district' => null, 'city' => null, 'region' => null]): array
    {
        return ['status' => $status, 'level' => $level, 'expected' => $expected, 'approximate' => $approximate, 'actual' => $actual];
    }

    /**
     * The warning to show for a result, or null when there is nothing to say.
     *
     * @param  array{status: string, level: string|null, expected: string|null, approximate: bool, actual: array{district: string|null, city: string|null, region: string|null}}  $result
     */
    public static function message(array $result): ?string
    {
        if ($result['status'] !== 'outside') {
            return null;
        }

        // Named down to the level that was checked, with the one above it
        // for context: «حي النرجس — الرياض».
        $actual = $result['actual'];
        $where = implode(' — ', array_filter(match ($result['level']) {
            'district' => [$actual['district'], $actual['city']],
            'city' => [$actual['city'], $actual['region']],
            default => [$actual['region']],
        })) ?: null;

        return __('geo_check.outside.'.$result['level'], [
            'expected' => $result['expected'],
            'actual' => $where ?? __('geo_check.unknown_place'),
        ]).($result['approximate'] ? ' '.__('geo_check.approximate_note') : '');
    }
}
