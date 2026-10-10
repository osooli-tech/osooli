<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\PortalSetting;
use App\Queries\OwnerParcelQuery;
use App\Support\Database\Dialect;
use App\Support\ParcelMassing;
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
    /**
     * Parcels held under the owner in someone else's name: on the map too,
     * marked as linked, carrying the holder's name and only the details the
     * administrator's switches allow.
     *
     * @return list<array<string, mixed>>
     */
    private function linkedFeatures(Owner $owner): array
    {
        $showValue = PortalSetting::allows(PortalSetting::SHOW_VALUE);
        $showDeed = PortalSetting::allows(PortalSetting::SHOW_DEED);

        return $owner->linkedParcels()
            ->whereNotNull('parcels.geom')
            ->select('parcels.*')
            ->selectRaw('ST_AsGeoJSON(parcels.geom, 6) AS geom_json')
            ->selectRaw('ST_Y(ST_Centroid(parcels.geom)) AS centroid_lat')
            ->selectRaw('ST_X(ST_Centroid(parcels.geom)) AS centroid_lng')
            ->with(['plan.district.city', 'heldDeed.owners'])
            ->get()
            ->map(function (Parcel $parcel) use ($showValue, $showDeed): array {
                $deed = $parcel->heldDeed;
                $lat = $parcel->getAttribute('centroid_lat');

                return [
                    'type' => 'Feature',
                    'geometry' => json_decode((string) $parcel->getAttribute('geom_json'), false),
                    'properties' => [
                        'id' => $parcel->id,
                        'linked' => true,
                        'parcel_no' => $parcel->parcel_no,
                        'geo_id' => $parcel->geo_id,
                        'asset_type' => $parcel->asset_type,
                        'plan_no' => $parcel->plan?->plan_no,
                        'district_name' => $parcel->plan?->district?->name_ar,
                        'city_name' => $parcel->plan?->district?->city?->name_ar,
                        'deed_no' => $showDeed ? $deed?->deed_no : null,
                        'deed_date_hijri' => $showDeed ? $deed?->deed_date_hijri : null,
                        'deed_status' => $showDeed ? $deed?->deed_status : null,
                        'deed_class' => $showDeed ? $deed?->deed_class : null,
                        'deed_area' => $deed?->deed_area,
                        'centroid_lat' => $lat === null ? null : (float) $lat,
                        'centroid_lng' => $lat === null ? null : (float) $parcel->getAttribute('centroid_lng'),
                        'm_price' => $showValue && $parcel->m_price !== null ? (float) $parcel->m_price : null,
                        'parcel_price' => $showValue && $parcel->parcel_price !== null ? (float) $parcel->parcel_price : null,
                        'is_priced' => $showValue && $parcel->m_price !== null,
                        'massing' => ParcelMassing::LINKED,
                        'owner_names' => $deed?->owners->pluck('name')->implode('، ') ?: null,
                    ],
                ];
            })
            ->values()
            ->all();
    }

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

        $features = $parcels->map(function (Parcel $parcel) use ($owner): array {
            // Only the deeds this owner is on; an earlier holder's deed is not theirs to see.
            $ownDeeds = $parcel->deeds->filter(fn ($deed) => $deed->deedOwners->contains('owner_id', $owner->id));
            $latestDeed = $ownDeeds->sortByDesc('id')->first();

            $owners = $ownDeeds
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
                    'deed_class' => $latestDeed?->deed_class,
                    'massing' => ParcelMassing::categoryOf($parcel->asset_type, $latestDeed?->deed_class),
                    'centroid_lat' => $parcel->getAttribute('centroid_lat') === null ? null : (float) $parcel->getAttribute('centroid_lat'),
                    'centroid_lng' => $parcel->getAttribute('centroid_lng') === null ? null : (float) $parcel->getAttribute('centroid_lng'),
                    'documents_count' => $parcel->photos_count,
                    'is_priced' => $parcel->m_price !== null,
                    // The owner's own figures — used to raise each parcel by its value on the 3D map.
                    'm_price' => $parcel->m_price === null ? null : (float) $parcel->m_price,
                    'parcel_price' => $parcel->parcel_price === null ? null : (float) $parcel->parcel_price,
                    'owner_names' => $owners->pluck('name')->implode('، ') ?: null,
                    'owner_ids' => $owners->pluck('id')->implode(',') ?: null,
                ],
            ];
        })->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => [...$features, ...$this->linkedFeatures($owner)]]);
    }
}
