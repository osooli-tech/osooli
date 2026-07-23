<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GeoJsonController extends Controller
{
    public function parcels(): JsonResponse
    {
        if (config('database.default') !== 'pgsql') {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        /** @var list<\stdClass> $rows */
        $rows = DB::select(
            'SELECT
                 p.id, p.parcel_no, p.geo_id, p.asset_type,
                 pl.plan_no,
                 d.name_ar AS district_name,
                 deed.deed_no, deed.deed_date_hijri, deed.deed_area, deed.deed_status,
                 ST_AsGeoJSON(p.geom, 6) AS geom_json,
                 ST_Y(ST_Centroid(p.geom)) AS centroid_lat,
                 ST_X(ST_Centroid(p.geom)) AS centroid_lng,
                 (SELECT COUNT(*) FROM parcel_photos WHERE parcel_id = p.id) AS documents_count,
                 -- A parcel can be co-owned, so owners are aggregated rather than joined
                 owners.names AS owner_names,
                 owners.ids AS owner_ids
             FROM parcels p
             LEFT JOIN plans pl ON pl.id = p.plan_id
             LEFT JOIN districts d ON d.id = pl.district_id
             LEFT JOIN LATERAL (
                 SELECT deed_no, deed_date_hijri, deed_area, deed_status
                 FROM deeds
                 WHERE parcel_id = p.id
                 ORDER BY id DESC
                 LIMIT 1
             ) deed ON true
             LEFT JOIN LATERAL (
                 SELECT string_agg(DISTINCT o.name, \' ، \') AS names,
                        string_agg(DISTINCT o.id::text, \',\') AS ids
                 FROM deeds dd
                 JOIN deed_owners dow ON dow.deed_id = dd.id
                 JOIN owners o ON o.id = dow.owner_id
                 WHERE dd.parcel_id = p.id
             ) owners ON true
             WHERE p.geom IS NOT NULL'
        );

        $features = array_map(static fn (\stdClass $row): array => [
            'type' => 'Feature',
            'geometry' => json_decode((string) ($row->geom_json ?? ''), false),
            'properties' => [
                'id' => $row->id,
                'parcel_no' => $row->parcel_no,
                'geo_id' => $row->geo_id,
                'asset_type' => $row->asset_type,
                'plan_no' => $row->plan_no,
                'district_name' => $row->district_name,
                'deed_no' => $row->deed_no,
                'deed_date_hijri' => $row->deed_date_hijri,
                'deed_area' => $row->deed_area,
                'deed_status' => $row->deed_status,
                'centroid_lat' => $row->centroid_lat,
                'centroid_lng' => $row->centroid_lng,
                'documents_count' => (int) $row->documents_count,
                'owner_names' => $row->owner_names,
                'owner_ids' => $row->owner_ids,
            ],
        ], $rows);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
