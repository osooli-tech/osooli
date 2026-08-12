<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trimmed parcel shape for list screens — no geometry, so lists stay light.
 *
 * @mixin Parcel
 */
class ParcelListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deed = $this->latestDeed;
        $district = $this->plan?->district;

        return [
            'id' => $this->id,
            'parcel_no' => $this->parcel_no,
            'geo_id' => $this->geo_id,
            'asset_type' => $this->asset_type,
            'plan_no' => $this->plan?->plan_no,
            'city' => $district?->city?->name_ar,
            'district' => $district?->name_ar,
            'area_sqm' => $deed?->deed_area === null ? null : (float) $deed->deed_area,
            'm_price' => $this->m_price === null ? null : (float) $this->m_price,
            'parcel_price' => $this->parcel_price === null ? null : (float) $this->parcel_price,
            'current_deed' => $deed === null ? null : [
                'id' => $deed->id,
                'deed_no' => $deed->deed_no,
                'deed_date_hijri' => $deed->deed_date_hijri,
                'deed_status' => $deed->deed_status,
            ],
            'documents_count' => $this->whenCounted('photos'),
            'centroid' => $this->centroid(),
        ];
    }

    /**
     * Centroid comes from a PostGIS expression selected alongside the model,
     * so it is read off the underlying attributes rather than a declared column.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function centroid(): ?array
    {
        $lat = $this->resource->getAttribute('centroid_lat');
        $lng = $this->resource->getAttribute('centroid_lng');

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }
}
