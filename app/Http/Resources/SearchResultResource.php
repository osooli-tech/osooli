<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A search hit, shaped as the title/subtitle/badge row the app renders.
 *
 * @mixin Parcel
 */
class SearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deed = $this->latestDeed;
        $district = $this->plan?->district;

        return [
            'type' => 'parcel',
            'id' => $this->id,
            'title' => __('parcels.parcel_no').' '.$this->parcel_no,
            'subtitle' => $this->subtitle(),
            'badge' => $deed?->deed_status,
        ];
    }

    /** Deed number and location, skipping whichever parts are missing. */
    private function subtitle(): string
    {
        $district = $this->plan?->district;
        $deedNo = $this->latestDeed?->deed_no;

        $location = array_filter([$district?->city?->name_ar, $district?->name_ar]);

        $parts = array_filter([
            $deedNo === null ? null : __('parcels.deed_no').' '.$deedNo,
            $location === [] ? null : implode(' / ', $location),
        ]);

        return implode(' · ', $parts);
    }
}
