<?php

declare(strict_types=1);

namespace App\Support\Geo;

use Illuminate\Support\Facades\DB;

/**
 * The ways to put a misplaced parcel's record back where its polygon is.
 *
 * A parcel has no district of its own: it takes its plan's, and the plan
 * takes its district's city. So the record is corrected one level up, for
 * every parcel sharing it:
 *
 *  - plan:     the polygon lies in another district that has a boundary —
 *              move the parcel's plan to that district;
 *  - district: the plan's district has no boundary and the polygon lies in
 *              another city — move the district to that city. The usual case
 *              for districts brought in from descriptive data, which the
 *              first importer filed under one default city (الدرعية).
 *
 * Each option counts how many of the parcels it moves lie in the target, so
 * a plan that straddles two districts is not moved on the strength of one.
 */
final class PlacementFix
{
    /**
     * @return list<array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}>
     */
    public static function options(int $parcelId): array
    {
        $row = DB::selectOne(
            'SELECT ST_X(ST_PointOnSurface(p.geom)) AS x, ST_Y(ST_PointOnSurface(p.geom)) AS y,
                    pl.id AS plan_id, pl.plan_no, d.id AS district_id, d.name_ar AS district,
                    CASE WHEN d.geom IS NULL THEN 0 ELSE 1 END AS district_bounded,
                    c.id AS city_id, c.name_ar AS city
             FROM parcels p
             JOIN plans pl ON pl.id = p.plan_id
             JOIN districts d ON d.id = pl.district_id
             JOIN cities c ON c.id = d.city_id
             WHERE p.id = ? AND p.deleted_at IS NULL',
            [$parcelId]
        );

        if ($row === null || $row->x === null) {
            return [];
        }

        $x = (float) $row->x;
        $y = (float) $row->y;
        $options = [];

        $districts = ParcelPlacement::containing('districts', $x, $y)['ids'];
        if ($districts !== [] && ! in_array((int) $row->district_id, $districts, true)) {
            $target = DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')
                ->where('d.id', $districts[0])->first(['d.name_ar', 'c.name_ar as city']);

            $options[] = [
                'kind' => 'plan',
                'subject_id' => (int) $row->plan_id,
                'subject' => (string) $row->plan_no,
                'target_id' => $districts[0],
                'from' => $row->district.' — '.$row->city,
                'to' => $target->name_ar.' — '.$target->city,
            ] + self::counts('districts', 'p.plan_id = ?', (int) $row->plan_id, $districts[0]);
        }

        $cities = ParcelPlacement::containing('cities', $x, $y)['ids'];
        if ((int) $row->district_bounded === 0 && $cities !== [] && ! in_array((int) $row->city_id, $cities, true)) {
            $options[] = [
                'kind' => 'district',
                'subject_id' => (int) $row->district_id,
                'subject' => (string) $row->district,
                'target_id' => $cities[0],
                'from' => (string) $row->city,
                'to' => (string) DB::table('cities')->where('id', $cities[0])->value('name_ar'),
            ] + self::counts('cities', 'p.plan_id IN (SELECT id FROM plans WHERE district_id = ?)', (int) $row->district_id, $cities[0]);
        }

        return $options;
    }

    /**
     * The option of this kind and target, if it still stands for the parcel —
     * checked again at the moment of the move, not trusted from when the
     * dialog opened.
     *
     * @return array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}|null
     */
    public static function find(int $parcelId, string $kind, int $targetId): ?array
    {
        foreach (self::options($parcelId) as $option) {
            if ($option['kind'] === $kind && $option['target_id'] === $targetId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * How many live parcels the move takes along, how many of them have a
     * polygon, and how many of those lie in the target.
     *
     * @return array{parcels: int, located: int, inside: int}
     */
    private static function counts(string $targetTable, string $parcelsWhere, int $subjectId, int $targetId): array
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS parcels,
                    SUM(CASE WHEN p.geom IS NULL THEN 0 ELSE 1 END) AS located,
                    SUM(CASE WHEN p.geom IS NOT NULL AND ST_Contains(t.geom, ST_PointOnSurface(p.geom)) THEN 1 ELSE 0 END) AS inside
             FROM parcels p CROSS JOIN {$targetTable} t
             WHERE {$parcelsWhere} AND t.id = ? AND p.deleted_at IS NULL",
            [$subjectId, $targetId]
        );

        return [
            'parcels' => (int) ($row->parcels ?? 0),
            'located' => (int) ($row->located ?? 0),
            'inside' => (int) ($row->inside ?? 0),
        ];
    }
}
