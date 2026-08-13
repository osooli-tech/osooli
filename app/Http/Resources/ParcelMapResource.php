<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One GeoJSON Feature per parcel, ready to drop into a map SDK.
 *
 * @mixin Parcel
 */
class ParcelMapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deed = $this->latestDeed;
        // Selected as a PostGIS expression, so it is not a declared column.
        $geoJson = $this->resource->getAttribute('geom_json');

        return [
            'type' => 'Feature',
            'geometry' => $geoJson === null ? null : json_decode((string) $geoJson, false),
            'properties' => [
                'id' => $this->id,
                'parcel_no' => $this->parcel_no,
                'asset_type' => $this->asset_type,
                'district' => $this->plan?->district?->name_ar,
                'city' => $this->plan?->district?->city?->name_ar,
                'plan_no' => $this->plan?->plan_no,
                'deed_no' => $deed?->deed_no,
                'deed_status' => $deed?->deed_status,
                'area_sqm' => $deed?->deed_area === null ? null : (float) $deed->deed_area,
                'm_price' => $this->m_price === null ? null : (float) $this->m_price,
                'parcel_price' => $this->parcel_price === null ? null : (float) $this->parcel_price,
                // First recorded owner on the latest deed — used to color parcels per owner.
                'owner_name' => $deed?->owners->first()?->name,
            ],
        ];
    }
}
