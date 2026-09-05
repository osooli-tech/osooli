<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Enums\PhotoType;
use App\Models\Parcel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembles the unified property file — the "digital twin" — for one parcel.
 *
 * Everything here comes from records we already hold. Nothing is estimated or
 * inferred: a field with no source reads as unavailable rather than being
 * filled with a plausible number.
 */
class DigitalTwinService
{
    /** Fields the completeness meter measures, grouped by the record holding them. */
    private const TRACKED_FIELDS = 13;

    public function for(Parcel $parcel): array
    {
        $parcel->loadMissing([
            'plan.district.city.region',
            'deeds.deedOwners.owner',
            'boundary.engineeringOffice',
            'surveyDecisions',
            'photos.deed',
            'currentDeed',
        ]);

        $computedArea = $this->computedArea($parcel);

        return [
            'identity' => $this->identity($parcel),
            'area' => $this->area($parcel, $computedArea),
            'valuation' => $this->valuation($parcel, $computedArea),
            'owners' => $this->owners($parcel),
            'documents' => $this->documents($parcel),
            'completeness' => $this->completeness($parcel),
        ];
    }

    /** Live area from PostGIS, in square metres. Never stored — always current. */
    private function computedArea(Parcel $parcel): ?float
    {
        /** @var \stdClass|null $row */
        $row = DB::selectOne(
            'SELECT ST_Area(geom::geography) AS area FROM parcels WHERE id = ? AND geom IS NOT NULL',
            [$parcel->id]
        );

        return $row?->area === null ? null : (float) $row->area;
    }

    private function identity(Parcel $parcel): array
    {
        $district = $parcel->plan?->district;

        return [
            'parcel_no' => $parcel->parcel_no,
            'spatial_id' => $parcel->geo_id,
            'plan_no' => $parcel->plan?->plan_no,
            'district' => $district?->name_ar,
            'city' => $district?->city?->name_ar,
            'region' => $district?->city?->region?->name_ar,
            'asset_type' => $parcel->asset_type,
            'land_transaction' => $parcel->land_transaction,
            'allocation_method' => $parcel->allocation_method,
            'fall_in' => $parcel->fall_in,
        ];
    }

    /**
     * The three areas are deliberately kept apart: the deed states one, the
     * field survey may measure another, and PostGIS computes a third from the
     * geometry. Presenting them together is the point — a mismatch is a finding.
     */
    private function area(Parcel $parcel, ?float $computed): array
    {
        $deedArea = $parcel->currentDeed->deed_area ?? $parcel->deeds->first()?->deed_area;
        $measured = $parcel->boundary?->measured_area;

        return [
            'deed' => $deedArea === null ? null : (float) $deedArea,
            'measured' => $measured === null ? null : (float) $measured,
            'computed' => $computed === null ? null : round($computed, 2),
        ];
    }

    /** Total value is the recorded price per square metre applied to the deed area. */
    private function valuation(Parcel $parcel, ?float $computedArea): array
    {
        $perMetre = $parcel->m_price === null ? null : (float) $parcel->m_price;
        $stored = $parcel->parcel_price === null ? null : (float) $parcel->parcel_price;
        $basis = $parcel->deeds->first()->deed_area ?? $computedArea;

        $derived = $perMetre !== null && $basis !== null
            ? round($perMetre * (float) $basis, 2)
            : null;

        return [
            'per_metre' => $perMetre,
            'total' => $stored ?? $derived,
            'is_derived' => $stored === null && $derived !== null,
        ];
    }

    /**
     * A parcel is often co-owned, and each deed carries its own owner list, so
     * names are collected across deeds and de-duplicated by owner id.
     *
     * @return list<array{id: int, name: string, national_id: string|null, share: string|null}>
     */
    private function owners(Parcel $parcel): array
    {
        /** @var array<int, array{id: int, name: string, national_id: string|null, share: string|null}> $rows */
        $rows = [];

        // Read through deed_owners rather than the pivot on the owners
        // relation: the share lives on that row, and this way it stays typed.
        foreach ($parcel->deeds as $deed) {
            foreach ($deed->deedOwners as $link) {
                $owner = $link->owner;

                if ($owner === null) {
                    continue;
                }

                // Keyed by id, so an owner holding several deeds appears once.
                $rows[$owner->id] ??= [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'national_id' => $owner->national_id,
                    // decimal:2 yields a string, the raw column a float — pinned
                    // to one type so the view never has to care which it got.
                    'share' => $link->ownership_share === null ? null : (string) $link->ownership_share,
                ];
            }
        }

        return array_values($rows);
    }

    /** @return array{deed: Collection<int, mixed>, survey: Collection<int, mixed>, images: Collection<int, mixed>} */
    private function documents(Parcel $parcel): array
    {
        $photos = $parcel->photos;

        return [
            'deed' => $photos->where('photo_type', PhotoType::Deed)->values(),
            'survey' => $photos->where('photo_type', PhotoType::BoundarySurvey)->values(),
            'images' => $photos->whereIn('photo_type', [PhotoType::Aerial, PhotoType::Ground])->values(),
        ];
    }

    /**
     * How much of the record is actually filled in. This replaces the kind of
     * scored "investment index" a mock-up can show but no data here supports —
     * it reports on the file itself, which we can answer honestly.
     */
    private function completeness(Parcel $parcel): array
    {
        $deed = $parcel->deeds->first();
        $boundary = $parcel->boundary;
        $decision = $parcel->surveyDecisions->first();

        $filled = collect([
            'asset_type' => $parcel->asset_type,
            'land_transaction' => $parcel->land_transaction,
            'allocation_method' => $parcel->allocation_method,
            'fall_in' => $parcel->fall_in,
            'price' => $parcel->m_price,
            'deed_no' => $deed?->deed_no,
            'deed_date' => $deed?->deed_date_hijri,
            'deed_status' => $deed?->deed_status,
            'deed_class' => $deed?->deed_class,
            'boundaries' => $boundary?->n_border,
            'measured_area' => $boundary?->measured_area,
            'qrar_no' => $decision?->qrar_no,
            'report_no' => $decision?->report_no,
        ])->reject(fn ($value) => $value === null || $value === '');

        return [
            'filled' => $filled->count(),
            'total' => self::TRACKED_FIELDS,
            'percent' => (int) round($filled->count() / self::TRACKED_FIELDS * 100),
            'missing' => collect([
                'asset_type', 'land_transaction', 'allocation_method', 'fall_in',
                'price', 'deed_no', 'deed_date', 'deed_status', 'deed_class',
                'boundaries', 'measured_area', 'qrar_no', 'report_no',
            ])->reject(fn (string $key) => $filled->has($key))->values(),
        ];
    }
}
