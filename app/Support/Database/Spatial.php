<?php

declare(strict_types=1);

namespace App\Support\Database;

use InvalidArgumentException;

/**
 * SQL fragments for the spatial work PostGIS and MariaDB spell differently.
 *
 * Most of what the application asks of the database is written the same in
 * both — ST_AsGeoJSON(g, n), ST_Centroid, ST_X/ST_Y, ST_Intersects — and stays
 * inline where it is used. Only the calls below differ, and they differ in
 * substance, not just in name:
 *
 *  - MariaDB has no geography type, so an area in square metres is derived
 *    from the planar area in square degrees, scaled by the length of a degree
 *    of latitude and of longitude at the polygon's centroid (WGS 84 series).
 *    For parcel-sized polygons the result agrees with PostGIS's spheroidal
 *    ST_Area to well under 0.1%.
 *  - MariaDB has no ST_Expand, no ST_Extent aggregate and no `&&` operator.
 *
 * Every method reads the dialect of the default connection at call time,
 * which the migrator and the sync command repoint for the length of a run.
 */
final class Spatial
{
    /** A geometry built from a GeoJSON bind parameter, as MultiPolygon in SRID 4326. */
    public static function fromGeoJson(string $param = '?'): string
    {
        return Dialect::isPostgres()
            ? "ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON({$param})), 4326)"
            // MariaDB's column is MULTIPOLYGON and it has no ST_Multi, so the
            // caller passes a MultiPolygon already — see multiPolygonJson().
            : "ST_GeomFromGeoJSON({$param})";
    }

    /** Area of a geometry expression in square metres. */
    public static function areaSqm(string $geometry): string
    {
        if (Dialect::isPostgres()) {
            return "ST_Area(({$geometry})::geography)";
        }

        $lat = "RADIANS(ST_Y(ST_Centroid({$geometry})))";

        return "(ST_Area({$geometry})"
            ." * (111132.954 - 559.822 * COS(2 * {$lat}) + 1.175 * COS(4 * {$lat}))"
            ." * (111412.84 * COS({$lat}) - 93.5 * COS(3 * {$lat})))";
    }

    /** Whether `$geometry` touches the bounding box of `$around` grown by `$margin` degrees. */
    public static function intersectsExpanded(string $geometry, string $around, float $margin): string
    {
        $margin = sprintf('%F', $margin);

        return Dialect::isPostgres()
            ? "ST_Intersects({$geometry}, ST_Expand({$around}, {$margin}))"
            : "ST_Intersects({$geometry}, ST_Buffer(ST_Envelope({$around}), {$margin}))";
    }

    /** A geometry from one WKT bind parameter, in SRID 4326. */
    public static function fromWkt(): string
    {
        return Dialect::isPostgres() ? 'ST_GeomFromText(?, 4326)' : 'ST_GeomFromText(?)';
    }

    /**
     * A geometry simplified by the tolerance in one bind parameter, in
     * degrees. MariaDB has no ST_Simplify, so there the geometry comes back
     * whole; the tolerance is still bound (and ignored) so the caller's
     * bindings are the same on both.
     */
    public static function simplify(string $geometry): string
    {
        return Dialect::isPostgres() ? "ST_Simplify({$geometry}, ?)" : "IF(? IS NULL, {$geometry}, {$geometry})";
    }

    /** A point from two bind parameters, longitude then latitude. */
    public static function point(): string
    {
        return Dialect::isPostgres() ? 'ST_SetSRID(ST_MakePoint(?, ?), 4326)' : 'Point(?, ?)';
    }

    /** Whether two geometries' bounding boxes overlap — the cheap pre-filter. */
    public static function boxesIntersect(string $a, string $b): string
    {
        return Dialect::isPostgres() ? "{$a} && {$b}" : "MBRIntersects({$a}, {$b})";
    }

    /**
     * Aggregate select list for the combined bounding box of a geometry
     * column, aliased x1, y1, x2, y2 (min lng, min lat, max lng, max lat).
     */
    public static function extentSelect(string $geometry): string
    {
        if (Dialect::isPostgres()) {
            return "ST_XMin(ST_Extent({$geometry})) AS x1, ST_YMin(ST_Extent({$geometry})) AS y1,"
                ." ST_XMax(ST_Extent({$geometry})) AS x2, ST_YMax(ST_Extent({$geometry})) AS y2";
        }

        // ST_Envelope's ring runs (minx miny), (maxx miny), (maxx maxy), …
        $min = "ST_PointN(ST_ExteriorRing(ST_Envelope({$geometry})), 1)";
        $max = "ST_PointN(ST_ExteriorRing(ST_Envelope({$geometry})), 3)";

        return "MIN(ST_X({$min})) AS x1, MIN(ST_Y({$min})) AS y1, MAX(ST_X({$max})) AS x2, MAX(ST_Y({$max})) AS y2";
    }

    /**
     * A GeoJSON geometry as a MultiPolygon document — what both databases'
     * columns hold. A bare Polygon is wrapped; anything else is refused.
     *
     * @param  array<string, mixed>|string  $geometry
     */
    public static function multiPolygonJson(array|string $geometry): string
    {
        $data = is_string($geometry) ? json_decode($geometry, true) : $geometry;

        if (! is_array($data) || ! is_array($data['coordinates'] ?? null)) {
            throw new InvalidArgumentException('Not a GeoJSON geometry.');
        }

        $coordinates = match ($data['type'] ?? null) {
            'MultiPolygon' => $data['coordinates'],
            'Polygon' => [$data['coordinates']],
            default => throw new InvalidArgumentException('Only Polygon and MultiPolygon are supported.'),
        };

        return (string) json_encode(['type' => 'MultiPolygon', 'coordinates' => $coordinates]);
    }
}
