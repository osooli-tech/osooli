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
                'deed_no' => $deed?->deed_no,
                'deed_status' => $deed?->deed_status,
                'area_sqm' => $deed?->deed_area === null ? null : (float) $deed->deed_area,
            ],
        ];
    }
}
