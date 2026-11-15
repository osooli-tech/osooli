<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PhotoType;
use App\Http\Resources\Concerns\ReportsHolders;
use App\Models\Deed;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deed row for the deeds-history screen, including its parcel and scan.
 *
 * @mixin Deed
 */
class DeedListResource extends JsonResource
{
    use ReportsHolders;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $parcel = $this->parcel;
        $district = $parcel?->plan?->district;
        // This deed's own scan — the parcel may also hold scans of other people's deeds.
        $document = $parcel?->photos->first(fn ($photo) => $photo->photo_type === PhotoType::Deed && $photo->deed_id === $this->id);

        // Reading as an account: the account's owners on this deed, and its
        // scan only where one of them lets the account read documents.
        $viewer = $request->user();
        $holders = null;
        if ($viewer instanceof Owner && $viewer->standsForMany()) {
            $onDeed = $this->owners->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $holders = array_values(array_intersect($viewer->holderIds(), $onDeed));
            if (array_intersect($holders, $this->documentReaderIds($request)) === []) {
                $document = null;
            }
        }

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
            'holder_ids' => $this->when($holders !== null, fn () => $holders),
        ];
    }
}
