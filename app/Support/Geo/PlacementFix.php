<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Database\Spatial;
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
    /** Plan numbers that stand for «no plan»: the importers' stand-in plans. */
    private const STAND_IN = 'بدون';

    /**
     * @return list<array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}>
     */
    public static function options(int $parcelId): array
    {
        $row = DB::selectOne(
            'SELECT ST_X(ST_PointOnSurface(p.geom)) AS x, ST_Y(ST_PointOnSurface(p.geom)) AS y,
                    p.parcel_no, p.geo_id,
                    pl.id AS plan_id, pl.plan_no, d.id AS district_id, d.name_ar AS district,
                    CASE WHEN d.geom IS NULL THEN 0 ELSE 1 END AS district_bounded,
                    c.id AS city_id, c.name_ar AS city, c.boundary_source AS city_source
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
        $district = self::officialAt('districts', $x, $y);
        $city = self::officialAt('cities', $x, $y);
        $from = $row->district.' — '.$row->city;

        // A parcel with no real plan belongs to no group that must stay
        // together: it alone goes where it lies.
        if (self::isStandIn((string) $row->plan_no)) {
            $place = $district ?? $city;
            if ($place === null || ($district !== null && $district->id === (int) $row->district_id)) {
                return [];
            }

            return [[
                'kind' => $district !== null ? 'parcel' : 'parcel_city',
                'subject_id' => $parcelId,
                'subject' => (string) ($row->geo_id ?? $row->parcel_no),
                'target_id' => $place->id,
                'from' => $from,
                'to' => $place->label,
                'parcels' => 1,
                'located' => 1,
                'inside' => 1,
            ]];
        }

        $options = [];

        if ($district !== null && $district->id !== (int) $row->district_id) {
            $options[] = [
                'kind' => 'plan',
                'subject_id' => (int) $row->plan_id,
                'subject' => (string) $row->plan_no,
                'target_id' => $district->id,
                'from' => $from,
                'to' => $district->label,
            ] + self::counts('districts', 'p.plan_id = ?', (int) $row->plan_id, $district->id);
        }

        if ($district === null && $city !== null && $city->id !== (int) $row->city_id) {
            // A village with no districts: the plan goes to a district named
            // after the village, inside it.
            $options[] = [
                'kind' => 'plan_city',
                'subject_id' => (int) $row->plan_id,
                'subject' => (string) $row->plan_no,
                'target_id' => $city->id,
                'from' => $from,
                'to' => $city->label,
            ] + self::counts('cities', 'p.plan_id = ?', (int) $row->plan_id, $city->id);
        }

        // An estimated village boundary cutting the plan in two: widen the
        // village the plan is in to take all of it — or move the plan to the
        // village the parcel is in and widen that one.
        $whole = self::counts('cities', 'p.plan_id = ?', (int) $row->plan_id, (int) $row->city_id);
        $whole['inside'] = $whole['located'];
        if ((int) $row->district_bounded === 0 && $row->city_source === 'approximate' && $city !== null && $city->id !== (int) $row->city_id) {
            $options[] = [
                'kind' => 'extend',
                'subject_id' => (int) $row->plan_id,
                'subject' => (string) $row->plan_no,
                'target_id' => (int) $row->city_id,
                'from' => (string) $row->city,
                'to' => (string) $row->city,
            ] + $whole;
        }
        if ($district === null && $city !== null && $city->id !== (int) $row->city_id && $city->source === 'approximate') {
            $options[] = [
                'kind' => 'plan_city_extend',
                'subject_id' => (int) $row->plan_id,
                'subject' => (string) $row->plan_no,
                'target_id' => $city->id,
                'from' => $from,
                'to' => $city->label,
            ] + $whole;
        }

        if ((int) $row->district_bounded === 0 && $city !== null && $city->id !== (int) $row->city_id) {
            $options[] = [
                'kind' => 'district',
                'subject_id' => (int) $row->district_id,
                'subject' => (string) $row->district,
                'target_id' => $city->id,
                'from' => (string) $row->city,
                'to' => $city->label,
            ] + self::counts('cities', 'p.plan_id IN (SELECT id FROM plans WHERE district_id = ?)', (int) $row->district_id, $city->id);
        }

        return $options;
    }

    /** Whether a plan number is a stand-in for «no plan» («بدون …»). */
    public static function isStandIn(string $planNo): bool
    {
        return str_starts_with(trim($planNo), self::STAND_IN);
    }

    /**
     * The district a parcel with no real plan goes into, for a target given
     * as a district (`parcel`) or as a village (`parcel_city`), and the
     * stand-in plan of that district it is filed under.
     */
    public static function standInPlanFor(string $kind, int $targetId): int
    {
        $districtId = $kind === 'parcel_city' ? self::villageDistrict($targetId) : $targetId;

        $place = DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')
            ->where('d.id', $districtId)->first(['d.name_ar', 'c.name_ar as city']);
        $planNo = mb_substr(implode(' — ', [self::STAND_IN, $place?->name_ar, $place?->city]), 0, 50);

        $plan = DB::table('plans')->where('plan_no', $planNo)->first(['id', 'district_id']);
        if ($plan !== null) {
            if ((int) $plan->district_id !== $districtId) {
                DB::table('plans')->where('id', $plan->id)->update(['district_id' => $districtId, 'updated_at' => now()]);
            }

            return (int) $plan->id;
        }

        return (int) DB::table('plans')->insertGetId([
            'plan_no' => $planNo,
            'district_id' => $districtId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The district standing for a village that has no districts of its own:
     * one named after the village, inside it — the one already there, or a
     * new one.
     */
    public static function villageDistrict(int $cityId): int
    {
        $name = (string) DB::table('cities')->where('id', $cityId)->value('name_ar');
        $existing = DB::table('districts')->where('city_id', $cityId)->where('name_ar', $name)->value('id');

        return $existing !== null ? (int) $existing : (int) DB::table('districts')->insertGetId([
            'city_id' => $cityId,
            'name_ar' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The official place holding the point: a district or city whose
     * boundary comes from the National Address or was drawn by hand — never
     * one drawn from parcels, which says where the parcels are, not where
     * the district is.
     *
     * @return object{id: int, label: string, source: string|null}|null
     */
    private static function officialAt(string $table, float $x, float $y): ?object
    {
        $point = Spatial::point();
        $city = $table === 'districts' ? ', (SELECT c.name_ar FROM cities c WHERE c.id = t.city_id) AS parent' : ', NULL AS parent';
        $row = DB::selectOne(
            "SELECT t.id, t.name_ar, t.boundary_source{$city} FROM {$table} t
             WHERE t.geom IS NOT NULL AND (t.boundary_source IS NULL OR t.boundary_source <> 'parcels')
               AND ".Spatial::boxesIntersect('t.geom', $point)." AND ST_Contains(t.geom, {$point})
             ORDER BY CASE t.boundary_source WHEN 'manual' THEN 0 WHEN 'official' THEN 1 WHEN 'derived' THEN 2 ELSE 3 END
             LIMIT 1",
            [$x, $y, $x, $y]
        );

        return $row === null ? null : (object) [
            'id' => (int) $row->id,
            'label' => $row->parent === null ? (string) $row->name_ar : $row->name_ar.' — '.$row->parent,
            'source' => $row->boundary_source === null ? null : (string) $row->boundary_source,
        ];
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
     * Where a whole plan's parcels lie, and the moves that follow from it:
     * to each district holding some of them, and — when the plan's district
     * has no boundary — the district to the city holding most of them.
     *
     * @return array{plan: string, current: string, parcels: int, located: int, spread: list<array{name: string, parcels: int}>, options: list<array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}>}|null
     */
    public static function forPlan(int $planId): ?array
    {
        $plan = DB::table('plans as pl')
            ->join('districts as d', 'd.id', '=', 'pl.district_id')
            ->join('cities as c', 'c.id', '=', 'd.city_id')
            ->where('pl.id', $planId)
            ->selectRaw('pl.id, pl.plan_no, d.id AS district_id, d.name_ar AS district, c.id AS city_id, c.name_ar AS city, CASE WHEN d.geom IS NULL THEN 0 ELSE 1 END AS district_bounded')
            ->first();

        if ($plan === null) {
            return null;
        }

        $counts = DB::selectOne(
            'SELECT COUNT(*) AS parcels, SUM(CASE WHEN geom IS NULL THEN 0 ELSE 1 END) AS located
             FROM parcels WHERE plan_id = ? AND deleted_at IS NULL',
            [$planId]
        );

        $districts = self::spread('districts', $planId);
        $cities = self::spread('cities', $planId);
        $options = [];

        foreach ($districts as $place) {
            if ($place->id !== (int) $plan->district_id) {
                $options[] = [
                    'kind' => 'plan',
                    'subject_id' => $planId,
                    'subject' => (string) $plan->plan_no,
                    'target_id' => $place->id,
                    'from' => $plan->district.' — '.$plan->city,
                    'to' => $place->name.' — '.$place->city,
                ] + self::counts('districts', 'p.plan_id = ?', $planId, $place->id);
            }
        }

        $topCity = $cities[0] ?? null;
        if ((int) $plan->district_bounded === 0 && $topCity !== null && $topCity->id !== (int) $plan->city_id) {
            $options[] = [
                'kind' => 'district',
                'subject_id' => (int) $plan->district_id,
                'subject' => (string) $plan->district,
                'target_id' => $topCity->id,
                'from' => (string) $plan->city,
                'to' => $topCity->name,
            ] + self::counts('cities', 'p.plan_id IN (SELECT id FROM plans WHERE district_id = ?)', (int) $plan->district_id, $topCity->id);
        }

        // Shown as where the parcels are: districts where any have one,
        // otherwise the cities.
        $spread = $districts !== [] ? $districts : $cities;

        return [
            'plan' => (string) $plan->plan_no,
            'current' => $plan->district.' — '.$plan->city,
            'parcels' => (int) ($counts->parcels ?? 0),
            'located' => (int) ($counts->located ?? 0),
            'spread' => array_map(static fn (object $p): array => [
                'name' => $p->city === null ? $p->name : $p->name.' — '.$p->city,
                'parcels' => $p->parcels,
            ], $spread),
            'options' => $options,
        ];
    }

    /**
     * The plan-level option of this kind and target, if it still stands.
     *
     * @return array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}|null
     */
    public static function findForPlan(int $planId, string $kind, int $targetId): ?array
    {
        foreach (self::forPlan($planId)['options'] ?? [] as $option) {
            if ($option['kind'] === $kind && $option['target_id'] === $targetId) {
                return $option;
            }
        }

        return null;
    }

    /**
     * The districts (or cities) with a boundary holding the plan's parcels,
     * most parcels first.
     *
     * @return list<object{id: int, name: string, city: string|null, parcels: int}>
     */
    private static function spread(string $table, int $planId): array
    {
        $point = 'ST_PointOnSurface(p.geom)';
        $cityJoin = $table === 'districts' ? 'JOIN cities c ON c.id = t.city_id' : '';
        $cityName = $table === 'districts' ? 'c.name_ar' : 'NULL';

        $rows = DB::select(
            "SELECT t.id, t.name_ar AS name, {$cityName} AS city, COUNT(*) AS parcels
             FROM parcels p
             JOIN {$table} t ON t.geom IS NOT NULL AND ".Spatial::boxesIntersect('t.geom', $point)." AND ST_Contains(t.geom, {$point})
             {$cityJoin}
             WHERE p.plan_id = ? AND p.geom IS NOT NULL AND p.deleted_at IS NULL
             GROUP BY t.id, t.name_ar".($table === 'districts' ? ', c.name_ar' : '').'
             ORDER BY COUNT(*) DESC, t.id
             LIMIT 5',
            [$planId]
        );

        return array_map(static fn (object $r): object => (object) [
            'id' => (int) $r->id,
            'name' => (string) $r->name,
            'city' => $r->city === null ? null : (string) $r->city,
            'parcels' => (int) $r->parcels,
        ], $rows);
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
