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
            'SELECT id, parcel_no, geo_id, asset_type, ST_AsGeoJSON(geom, 6) AS geom_json
             FROM parcels
             WHERE geom IS NOT NULL'
        );

        $features = array_map(static fn (\stdClass $row): array => [
            'type' => 'Feature',
            'geometry' => json_decode((string) ($row->geom_json ?? ''), false),
            'properties' => [
                'id' => $row->id,
                'parcel_no' => $row->parcel_no,
                'geo_id' => $row->geo_id,
                'asset_type' => $row->asset_type,
            ],
        ], $rows);

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
