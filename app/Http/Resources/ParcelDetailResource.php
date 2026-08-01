<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full parcel shape for the detail screen, including geometry and documents.
 *
 * Prices (m_price / parcel_price) are deliberately never exposed to the app.
 *
 * @mixin Parcel
 */
class ParcelDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $district = $this->plan?->district;
        $latestDeedId = $this->latestDeed?->id;

        return [
            'id' => $this->id,
            'parcel_no' => $this->parcel_no,
            'geo_id' => $this->geo_id,
            'asset_type' => $this->asset_type,
            'land_transaction' => $this->land_transaction,
            'allocation_method' => $this->allocation_method,
            'fall_in' => $this->fall_in,
            'plan_no' => $this->plan?->plan_no,
            'city' => $district?->city?->name_ar,
            'district' => $district?->name_ar,

            'centroid' => $this->centroid(),
            'corners' => $this->corners(),
            'geometry' => $this->geometry(),

            'owners' => $this->owners(),
            'deeds' => $this->deeds->map(fn (Deed $deed) => array_merge(
                (new DeedResource($deed))->toArray($request),
                ['is_current' => $deed->id === $latestDeedId],
            ))->all(),

            'boundary' => $this->boundary(),
            'survey_decision' => $this->surveyDecision(),
            'documents' => DocumentResource::collection($this->photos)->toArray($request),
        ];
    }

    /**
     * Centroid and geometry come from PostGIS expressions selected alongside
     * the model, so they are read off the underlying attributes.
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

    private function geometry(): ?object
    {
        $geoJson = $this->resource->getAttribute('geom_json');

        return $geoJson === null ? null : json_decode((string) $geoJson, false);
    }

    /**
     * Corner points of the exterior ring, with the closing duplicate dropped.
     *
     * @return list<array{lat: float, lng: float}>
     */
    private function corners(): array
    {
        $geometry = $this->geometry();

        if ($geometry === null || ! isset($geometry->coordinates)) {
            return [];
        }

        $ring = $geometry->type === 'Polygon'
            ? ($geometry->coordinates[0] ?? [])
            : ($geometry->coordinates[0][0] ?? []);

        $points = array_slice((array) $ring, 0, -1);

        return array_map(
            static fn (array $point): array => ['lat' => (float) $point[1], 'lng' => (float) $point[0]],
            $points
        );
    }

    /**
     * A parcel can be co-owned, so this is always a list.
     *
     * @return list<array<string, mixed>>
     */
    private function owners(): array
    {
        $owners = [];

        foreach ($this->deeds as $deed) {
            foreach ($deed->owners as $owner) {
                /** @var Owner $owner */
                $share = $owner->getRelationValue('pivot')?->getAttribute('ownership_share');

                $owners[$owner->id] = [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'national_id' => $owner->national_id,
                    'ownership_share' => $share === null ? null : (float) $share,
                ];
            }
        }

        return array_values($owners);
    }

    /** @return array<string, mixed>|null */
    private function boundary(): ?array
    {
        $boundary = $this->boundary;

        if ($boundary === null) {
            return null;
        }

        return [
            'north' => ['description' => $boundary->n_border, 'length_m' => $this->toFloat($boundary->n_dim)],
            'south' => ['description' => $boundary->s_border, 'length_m' => $this->toFloat($boundary->s_dim)],
            'east' => ['description' => $boundary->e_border, 'length_m' => $this->toFloat($boundary->e_dim)],
            'west' => ['description' => $boundary->w_border, 'length_m' => $this->toFloat($boundary->w_dim)],
            'measured_area_sqm' => $this->toFloat($boundary->measured_area),
            'matches_deed' => $boundary->matches_deed,
            'engineering_office' => $boundary->engineeringOffice?->name,
        ];
    }

    /** @return array<string, mixed>|null */
    private function surveyDecision(): ?array
    {
        $decision = $this->surveyDecisions->first();

        if ($decision === null) {
            return null;
        }

        return [
            'qrar_no' => $decision->qrar_no,
            'report_no' => $decision->report_no,
            'qrar_source' => $decision->qrar_source?->value,
            'folder' => $decision->folder,
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
