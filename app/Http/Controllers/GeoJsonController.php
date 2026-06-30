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
                 deed.deed_no, deed.deed_date_hijri,
                 ST_AsGeoJSON(p.geom, 6) AS geom_json
             FROM parcels p
             LEFT JOIN plans pl ON pl.id = p.plan_id
             LEFT JOIN districts d ON d.id = pl.district_id
             LEFT JOIN LATERAL (
                 SELECT deed_no, deed_date_hijri
                 FROM deeds
                 WHERE parcel_id = p.id
                 ORDER BY id DESC
                 LIMIT 1
             ) deed ON true
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
            ],
        ], $rows);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
