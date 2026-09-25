<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Parcel;
use App\Models\Project;
use App\Models\User;
use App\Support\Database\Dialect;
use App\Support\Database\Spatial;
use App\Support\OwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GeoJsonController extends Controller
{
    private const BOUNDARY_LEVELS = ['regions', 'cities', 'districts'];

    public function parcels(): JsonResponse
    {
        if (! Dialect::isSpatial()) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        /** @var User|null $user */
        $user = Auth::user();
        $parcelIds = OwnerScope::parcelIds($user);

        // Eager-loading deeds.deedOwners.owner covers both the "latest deed"
        // fields and the owner-name aggregation below from the same rows —
        // one extra query total, not one per parcel.
        $parcels = Parcel::query()
            ->with(['plan.district', 'deeds.deedOwners.owner'])
            ->withCount('photos')
            ->whereNotNull('geom')
            ->when($parcelIds !== null, fn ($q) => $q->whereIn('id', $parcelIds))
            ->selectRaw('parcels.*, ST_AsGeoJSON(geom, 6) AS geom_json, ST_Y(ST_Centroid(geom)) AS centroid_lat, ST_X(ST_Centroid(geom)) AS centroid_lng')
            ->get();

        $features = $parcels->map(function (Parcel $parcel): array {
            // Most recently recorded deed, whatever its status — matches
            // what a parcel's own detail page treats as "the" deed to show
            // a headline for, not only ones marked as currently valid.
            $latestDeed = $parcel->deeds->sortByDesc('id')->first();

            // A parcel can be co-owned, and ownership can also change hands
            // across several deeds, so owners are aggregated across every
            // deed on record rather than just the latest one.
            $owners = $parcel->deeds
                ->flatMap(fn ($deed) => $deed->deedOwners)
                ->pluck('owner')
                ->filter()
                ->unique('id');

            return [
                'type' => 'Feature',
                'geometry' => json_decode((string) $parcel->getAttribute('geom_json'), false),
                'properties' => [
                    'id' => $parcel->id,
                    'parcel_no' => $parcel->parcel_no,
                    'geo_id' => $parcel->geo_id,
                    'asset_type' => $parcel->asset_type,
                    'fall_in' => $parcel->fall_in,
                    'plan_no' => $parcel->plan?->plan_no,
                    'district_name' => $parcel->plan?->district?->name_ar,
                    'deed_no' => $latestDeed?->deed_no,
                    'deed_date_hijri' => $latestDeed?->deed_date_hijri,
                    'deed_area' => $latestDeed?->deed_area,
                    'deed_status' => $latestDeed?->deed_status,
                    'centroid_lat' => (float) $parcel->getAttribute('centroid_lat'),
                    'centroid_lng' => (float) $parcel->getAttribute('centroid_lng'),
                    'documents_count' => $parcel->photos_count,
                    // Drives the priced/unpriced colouring; the amount itself stays out.
                    'is_priced' => $parcel->m_price !== null,
                    'owner_names' => $owners->pluck('name')->implode('، ') ?: null,
                    'owner_ids' => $owners->pluck('id')->implode(',') ?: null,
                ],
            ];
        })->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /**
     * A pure identification/display layer, per the client's own description
     * of it — name and shape only, no ownership or scoping: unlike parcels,
     * a project zone or building routinely overlaps more than one parcel,
     * so it isn't tied to a single owner to filter by in the first place.
     */
    public function projects(): JsonResponse
    {
        return $this->displayLayer(Project::class);
    }

    public function buildings(): JsonResponse
    {
        return $this->displayLayer(Building::class);
    }

    /**
     * Region, city or district boundaries inside the map's current view,
     * simplified to about a pixel at its zoom — the full district layer is
     * tens of megabytes, far too much to send whole.
     */
    public function boundaries(Request $request, string $level): JsonResponse
    {
        abort_unless(in_array($level, self::BOUNDARY_LEVELS, true), 404);

        if (! Dialect::isSpatial()) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        $box = array_map('floatval', explode(',', (string) $request->query('bbox', '34,15,56,33')));
        if (count($box) !== 4) {
            abort(422);
        }
        [$west, $south, $east, $north] = $box;
        $zoom = max(0.0, min(22.0, (float) $request->query('zoom', 6)));
        // Degrees per screen pixel at this zoom, roughly.
        $tolerance = max(0.000005, 180 / (2 ** ($zoom + 8)));

        $view = sprintf('POLYGON((%1$F %2$F,%3$F %2$F,%3$F %4$F,%1$F %4$F,%1$F %2$F))', $west, $south, $east, $north);

        $rows = DB::select(
            "SELECT id, name_ar, name_en, boundary_source, ST_AsGeoJSON(ST_Simplify(geom, ?), 6) AS geom_json
             FROM {$level}
             WHERE geom IS NOT NULL AND ".Spatial::boxesIntersect('geom', Spatial::fromWkt()).'
             LIMIT 2000',
            [$tolerance, $view]
        );

        $english = app()->isLocale('en');

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_values(array_filter(array_map(static fn (object $row): ?array => $row->geom_json === null ? null : [
                'type' => 'Feature',
                'id' => (int) $row->id,
                'geometry' => json_decode((string) $row->geom_json, false),
                'properties' => [
                    'name' => $english && $row->name_en ? $row->name_en : $row->name_ar,
                    'source' => $row->boundary_source,
                ],
            ], $rows))),
        ]);
    }

    /** @param class-string<Project|Building> $modelClass */
    private function displayLayer(string $modelClass): JsonResponse
    {
        if (! Dialect::isSpatial()) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        $rows = $modelClass::query()
            ->whereNotNull('geom')
            ->selectRaw('id, name, code, area, length, ST_AsGeoJSON(geom, 6) AS geom_json')
            ->get();

        $features = $rows->map(static fn (Model $row): array => [
            'type' => 'Feature',
            'geometry' => json_decode((string) $row->getAttribute('geom_json'), false),
            'properties' => [
                'id' => $row->getAttribute('id'),
                'name' => $row->getAttribute('name'),
                'code' => $row->getAttribute('code'),
                'area' => $row->getAttribute('area'),
                'length' => $row->getAttribute('length'),
            ],
        ])->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
