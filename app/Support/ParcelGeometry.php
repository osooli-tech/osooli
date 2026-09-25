<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Database\Dialect;
use App\Support\Database\Spatial;
use App\Support\Geo\GeometryMath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reading, checking and replacing a parcel's polygon.
 *
 * Everything that touches `parcels.geom` from the dashboard goes through here,
 * so the rules a drawn polygon must meet are written once. On PostgreSQL,
 * PostGIS does the judging — validity, area, bounds — rather than a hand-rolled
 * PHP check that would disagree with it on the edge cases. MariaDB can give
 * neither an area in square metres nor a reason for invalidity, so there
 * GeometryMath takes the same measurements in PHP.
 */
final class ParcelGeometry
{
    /**
     * The kingdom plus a margin, in degrees. Not a political boundary: its job
     * is to catch a polygon whose longitude and latitude were swapped, or that
     * was drawn after panning to the wrong continent.
     */
    private const BOUNDS = ['min_lng' => 34.0, 'max_lng' => 56.5, 'min_lat' => 15.5, 'max_lat' => 33.0];

    /** Smaller than this is a stray click, not a parcel. */
    private const MIN_AREA_SQM = 1.0;

    /** 25 km² — far beyond any parcel, well short of a whole district. */
    private const MAX_AREA_SQM = 25_000_000.0;

    /** Vertices per submission; a traced parcel needs tens, not thousands. */
    private const MAX_POINTS = 5000;

    /** Payload ceiling before any parsing, in bytes. */
    private const MAX_BYTES = 1_000_000;

    /** A region drawn in full detail runs to tens of thousands of vertices. */
    private const BOUNDARY_MAX_POINTS = 50_000;

    private const BOUNDARY_MAX_BYTES = 3_000_000;

    /** Overlap below this many m² is digitising noise along a shared edge. */
    private const OVERLAP_TOLERANCE_SQM = 1.0;

    /** The parcel's polygon as GeoJSON, or null when it has none yet. */
    public static function current(int $parcelId): ?string
    {
        $row = DB::selectOne('SELECT ST_AsGeoJSON(geom, 7) AS geojson FROM parcels WHERE id = ?', [$parcelId]);

        return $row?->geojson;
    }

    /**
     * Surrounding parcels as a FeatureCollection, for tracing against.
     *
     * With a polygon, the parcels touching a small box around it. Without one
     * — a parcel about to get its first polygon — the parcels of its plan,
     * which is where it will have to be drawn.
     */
    public static function neighbours(int $parcelId): string
    {
        $rows = DB::select(
            'SELECT n.parcel_no, ST_AsGeoJSON(n.geom, 7) AS geojson
             FROM parcels n
             JOIN parcels self ON self.id = ?
             WHERE n.id <> self.id
               AND n.geom IS NOT NULL
               AND n.deleted_at IS NULL
               AND (
                    (self.geom IS NOT NULL AND '.Spatial::intersectsExpanded('n.geom', 'self.geom', 0.004).')
                 OR (self.geom IS NULL AND self.plan_id IS NOT NULL AND n.plan_id = self.plan_id)
               )
             LIMIT 200',
            [$parcelId]
        );

        return (string) json_encode([
            'type' => 'FeatureCollection',
            'features' => array_map(static fn (\stdClass $row): array => [
                'type' => 'Feature',
                'geometry' => json_decode((string) $row->geojson, false),
                'properties' => ['parcel_no' => $row->parcel_no],
            ], $rows),
        ]);
    }

    /**
     * Where to point the map for a parcel that has no polygon: its plan's
     * extent, or failing that the extent of every mapped parcel.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null [minLng, minLat, maxLng, maxLat]
     */
    public static function fallbackBounds(int $parcelId): ?array
    {
        $row = DB::selectOne(
            'SELECT '.Spatial::extentSelect('n.geom').'
             FROM parcels n JOIN parcels self ON self.id = ?
             WHERE n.plan_id = self.plan_id AND n.geom IS NOT NULL AND n.deleted_at IS NULL',
            [$parcelId]
        );

        if ($row === null || $row->x1 === null) {
            $row = DB::selectOne(
                'SELECT '.Spatial::extentSelect('geom').' FROM parcels WHERE geom IS NOT NULL AND deleted_at IS NULL'
            );
        }

        if ($row === null || $row->x1 === null) {
            return null;
        }

        return [(float) $row->x1, (float) $row->y1, (float) $row->x2, (float) $row->y2];
    }

    /**
     * Check a submitted polygon and return it normalised to a MultiPolygon.
     *
     * Accepts a bare Polygon or MultiPolygon, or a Feature / FeatureCollection
     * of them, because that is what a pasted GeoJSON file tends to be.
     *
     * @return array{geojson: string, area: float}
     *
     * @throws InvalidArgumentException with a translated, user-facing message
     */
    public static function validate(string $input): array
    {
        return self::checked($input, self::MAX_BYTES, self::MAX_POINTS, self::MAX_AREA_SQM);
    }

    /**
     * The same checks for an administrative boundary — a district, city or
     * region — which is many times the size and detail of any parcel. Only
     * the limits differ; validity and the national bounds are held the same.
     *
     * @return array{geojson: string, area: float}
     *
     * @throws InvalidArgumentException with a translated, user-facing message
     */
    public static function validateBoundary(string $input): array
    {
        return self::checked($input, self::BOUNDARY_MAX_BYTES, self::BOUNDARY_MAX_POINTS, INF);
    }

    /**
     * @return array{geojson: string, area: float}
     *
     * @throws InvalidArgumentException
     */
    private static function checked(string $input, int $maxBytes, int $maxPoints, float $maxArea): array
    {
        if ($input === '' || strlen($input) > $maxBytes) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.malformed'));
        }

        $geometry = self::toMultiPolygon(json_decode($input, true));

        $geojson = (string) json_encode($geometry);

        $row = Dialect::isPostgres() ? self::measureInPostgis($geojson) : self::measureInPhp($geometry);

        // Checked before validity: an oversized polygon is refused either
        // way, and the vertex count is the plainer reason to give.
        if ((int) $row->points > $maxPoints) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.too_many_points', ['max' => $maxPoints]));
        }

        if (! $row->valid) {
            // Self-intersection is by far the common case — a vertex dragged
            // across the opposite edge — so it gets a message a person can act
            // on; anything rarer falls back to the checker's own reason.
            $reason = (string) $row->reason;

            throw new InvalidArgumentException(str_contains(strtolower($reason), 'self-intersection')
                ? __('parcels.geometry_errors.self_intersection')
                : __('parcels.geometry_errors.invalid', ['reason' => $reason]));
        }

        if ((float) $row->x1 < self::BOUNDS['min_lng'] || (float) $row->x2 > self::BOUNDS['max_lng']
            || (float) $row->y1 < self::BOUNDS['min_lat'] || (float) $row->y2 > self::BOUNDS['max_lat']) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.out_of_bounds'));
        }

        $area = (float) $row->area;

        if ($area < self::MIN_AREA_SQM || $area > $maxArea) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.area', [
                'area' => number_format($area, 2),
            ]));
        }

        return ['geojson' => $geojson, 'area' => $area];
    }

    /**
     * Live parcels the polygon would overlap by more than digitising noise.
     *
     * A warning, not a refusal: overlapping parcels do exist — a sub-unit sits
     * inside its building — but a new overlap is usually a slip worth a second
     * look.
     *
     * @return list<string> their parcel numbers (or GEO ids)
     */
    public static function overlaps(int $parcelId, string $geojson): array
    {
        $rows = DB::select(
            'SELECT COALESCE(NULLIF(n.parcel_no, \'\'), n.geo_id) AS label
             FROM parcels n,
                  (SELECT '.Spatial::fromGeoJson().' AS g) s
             WHERE n.id <> ?
               AND n.geom IS NOT NULL
               AND n.deleted_at IS NULL
               AND '.Spatial::boxesIntersect('n.geom', 's.g').'
               AND '.Spatial::areaSqm('ST_Intersection(n.geom, s.g)').' > ?
             ORDER BY 1
             LIMIT 20',
            [$geojson, $parcelId, self::OVERLAP_TOLERANCE_SQM]
        );

        return array_map(static fn (\stdClass $row): string => (string) $row->label, $rows);
    }

    /**
     * Replace the parcel's polygon, keeping the one it replaces as a revision.
     *
     * The caller wraps this in its transaction and has already locked the
     * parcel row, so the revision captured is exactly the polygon replaced.
     */
    public static function replace(int $parcelId, ?string $geojson, string $action): void
    {
        DB::insert(
            'INSERT INTO parcel_geometry_revisions (parcel_id, geom, area_sqm, action, user_id, created_at)
             SELECT id, geom, '.Spatial::areaSqm('geom').', ?, ?, NOW() FROM parcels WHERE id = ?',
            [$action, Auth::id(), $parcelId]
        );

        if ($geojson === null) {
            DB::update('UPDATE parcels SET geom = NULL, updated_at = NOW() WHERE id = ?', [$parcelId]);

            return;
        }

        DB::update(
            'UPDATE parcels SET geom = '.Spatial::fromGeoJson().', updated_at = NOW() WHERE id = ?',
            [Spatial::multiPolygonJson($geojson), $parcelId]
        );
    }

    /**
     * The polygon a revision holds, as GeoJSON; null if the parcel had none.
     */
    public static function revisionGeometry(int $parcelId, int $revisionId): ?string
    {
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom, 7) AS geojson FROM parcel_geometry_revisions WHERE id = ? AND parcel_id = ?',
            [$revisionId, $parcelId]
        );

        if ($row === null) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.revision_missing'));
        }

        return $row->geojson;
    }

    /**
     * The most recent revisions of a parcel, newest first.
     *
     * @return list<\stdClass> id, action, area_sqm, has_geom, created_at, user_name
     */
    public static function revisions(int $parcelId, int $limit = 10): array
    {
        return DB::select(
            'SELECT r.id, r.action, r.area_sqm, (r.geom IS NOT NULL) AS has_geom, r.created_at, u.name AS user_name
             FROM parcel_geometry_revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.parcel_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ?',
            [$parcelId, $limit]
        );
    }

    /**
     * Validity, area, vertex count and bounds of a polygon, judged by PostGIS.
     *
     * @return \stdClass valid, reason, area, points, x1, y1, x2, y2
     */
    private static function measureInPostgis(string $geojson): \stdClass
    {
        try {
            $row = DB::selectOne(
                'SELECT ST_IsValid(g) AS valid,
                        ST_IsValidReason(g) AS reason,
                        ST_Area(g::geography) AS area,
                        ST_NPoints(g) AS points,
                        ST_XMin(g) AS x1, ST_YMin(g) AS y1, ST_XMax(g) AS x2, ST_YMax(g) AS y2
                 FROM (SELECT ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(?)), 4326) AS g) s',
                [$geojson]
            );
        } catch (QueryException) {
            // ST_GeomFromGeoJSON rejects what json_decode accepted — an
            // unclosed ring, a ring of fewer than four positions.
            throw new InvalidArgumentException(__('parcels.geometry_errors.malformed'));
        }

        if ($row === null) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.malformed'));
        }

        return $row;
    }

    /**
     * The same measurements taken in PHP, for a database without PostGIS.
     *
     * @param  array{type: 'MultiPolygon', coordinates: list<mixed>}  $geometry
     * @return \stdClass valid, reason, area, points, x1, y1, x2, y2
     */
    private static function measureInPhp(array $geometry): \stdClass
    {
        // What ST_GeomFromGeoJSON would have refused: a position that is not
        // a pair of numbers, or a ring that is not a list of positions.
        foreach ($geometry['coordinates'] as $polygon) {
            foreach (is_array($polygon) ? $polygon : [null] as $ring) {
                foreach (is_array($ring) ? $ring : [null] as $position) {
                    if (! is_array($position) || ! is_numeric($position[0] ?? null) || ! is_numeric($position[1] ?? null)) {
                        throw new InvalidArgumentException(__('parcels.geometry_errors.malformed'));
                    }
                }
            }
        }

        $measured = GeometryMath::measure($geometry['coordinates']);

        return (object) [
            'valid' => $measured['valid'],
            'reason' => $measured['reason'],
            'area' => $measured['area'],
            'points' => $measured['points'],
            'x1' => $measured['bbox'][0],
            'y1' => $measured['bbox'][1],
            'x2' => $measured['bbox'][2],
            'y2' => $measured['bbox'][3],
        ];
    }

    /**
     * Unwrap Feature / FeatureCollection and merge everything into one
     * MultiPolygon.
     *
     * @return array{type: 'MultiPolygon', coordinates: list<mixed>}
     */
    private static function toMultiPolygon(mixed $data): array
    {
        if (! is_array($data) || ! isset($data['type']) || ! is_string($data['type'])) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.malformed'));
        }

        $geometries = match ($data['type']) {
            'FeatureCollection' => array_map(
                static fn (mixed $feature): mixed => is_array($feature) ? ($feature['geometry'] ?? null) : null,
                is_array($data['features'] ?? null) ? $data['features'] : []
            ),
            'Feature' => [$data['geometry'] ?? null],
            default => [$data],
        };

        $polygons = [];

        foreach ($geometries as $geometry) {
            if (! is_array($geometry) || ! is_array($geometry['coordinates'] ?? null)) {
                throw new InvalidArgumentException(__('parcels.geometry_errors.not_polygon'));
            }

            match ($geometry['type'] ?? null) {
                'Polygon' => $polygons[] = $geometry['coordinates'],
                'MultiPolygon' => array_push($polygons, ...array_values($geometry['coordinates'])),
                default => throw new InvalidArgumentException(__('parcels.geometry_errors.not_polygon')),
            };
        }

        if ($polygons === []) {
            throw new InvalidArgumentException(__('parcels.geometry_errors.empty'));
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }
}
