<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PhotoType;
use App\Models\Deed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deed row for the deeds-history screen, including its parcel and scan.
 *
 * @mixin Deed
 */
class DeedListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $parcel = $this->parcel;
        $district = $parcel?->plan?->district;
        // The deed scan is stored against the parcel, typed "صك".
        $document = $parcel?->photos->firstWhere('photo_type', PhotoType::Deed);

        return [
            'id' => $this->id,
            'deed_no' => $this->deed_no,
            'deed_date_hijri' => $this->deed_date_hijri,
            'deed_area_sqm' => $this->deed_area === null ? null : (float) $this->deed_area,
            'deed_status' => $this->deed_status,
            'deed_class' => $this->deed_class,
            'parcel' => $parcel === null ? null : [
                'id' => $parcel->id,
                'parcel_no' => $parcel->parcel_no,
                'plan_no' => $parcel->plan?->plan_no,
                'city' => $district?->city?->name_ar,
                'district' => $district?->name_ar,
            ],
            'document' => $document === null ? null : [
                'id' => $document->id,
                'download_url' => route('api.documents.download', $document->id),
            ],
        ];
    }
}
