<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Database\Spatial;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Which district and city a parcel's polygon lies in, by the boundaries on
 * record — tested at a point on its surface, so a crescent-shaped parcel
 * counts where it actually is. Boundaries drawn by hand win over official
 * ones, official over derived or approximate.
 */
final class Locator
{
    /**
     * @param  mixed  $geometry  a GeoJSON Polygon or MultiPolygon
     * @return array{district: int|null, city: int|null}|null null when the shape cannot be read
     */
    public static function locate(mixed $geometry): ?array
    {
        if (! is_array($geometry)) {
            return null;
        }

        try {
            $json = Spatial::multiPolygonJson($geometry);
        } catch (InvalidArgumentException) {
            return null;
        }

        $inside = static fn (string $table): string => "(SELECT t.id FROM {$table} t WHERE t.geom IS NOT NULL AND "
            .Spatial::boxesIntersect('t.geom', 's.p').' AND ST_Contains(t.geom, s.p)'
            ." ORDER BY CASE t.boundary_source WHEN 'manual' THEN 0 WHEN 'official' THEN 1 WHEN 'derived' THEN 2 ELSE 3 END LIMIT 1)";

        try {
            $row = DB::selectOne(
                'SELECT '.$inside('districts').' AS district_id, '.$inside('cities').' AS city_id
                 FROM (SELECT ST_PointOnSurface('.Spatial::fromGeoJson().') AS p) s',
                [$json]
            );
        } catch (QueryException) {
            return null;
        }

        return [
            'district' => $row?->district_id === null ? null : (int) $row->district_id,
            'city' => $row?->city_id === null ? null : (int) $row->city_id,
        ];
    }
}
