<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Parcel;
use App\Queries\OwnerParcelQuery;
use App\Support\Database\Dialect;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The portal map's own parcel feed — the same shape map.js already expects
 * from the dashboard's feed, so the file loads there completely unmodified,
 * but scoped to the signed-in owner via OwnerParcelQuery instead of
 * OwnerScope (a different, unrelated restriction meant for internal users).
 */
class GeoJsonController extends Controller
{
    public function parcels(): JsonResponse
    {
        if (! Dialect::isSpatial()) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        $parcels = (new OwnerParcelQuery($owner))
            ->withGeometry()
            ->with(['plan.district.city', 'deeds.deedOwners.owner'])
            ->withCount('photos')
            ->get();

        $features = $parcels->map(function (Parcel $parcel): array {
            $latestDeed = $parcel->deeds->sortByDesc('id')->first();

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
                    'city_name' => $parcel->plan?->district?->city?->name_ar,
                    'deed_no' => $latestDeed?->deed_no,
                    'deed_date_hijri' => $latestDeed?->deed_date_hijri,
                    'deed_area' => $latestDeed?->deed_area,
                    'deed_status' => $latestDeed?->deed_status,
                    'centroid_lat' => $parcel->getAttribute('centroid_lat') === null ? null : (float) $parcel->getAttribute('centroid_lat'),
                    'centroid_lng' => $parcel->getAttribute('centroid_lng') === null ? null : (float) $parcel->getAttribute('centroid_lng'),
                    'documents_count' => $parcel->photos_count,
                    'is_priced' => $parcel->m_price !== null,
                    'owner_names' => $owners->pluck('name')->implode('، ') ?: null,
                    'owner_ids' => $owners->pluck('id')->implode(',') ?: null,
                ],
            ];
        })->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
