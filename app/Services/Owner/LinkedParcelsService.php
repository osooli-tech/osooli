<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\PortalSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Parcels held under an owner but in someone else's name
 * (parcels.parent_owner_id), as the parent owner sees them in the portal.
 *
 * They are never mixed into the owner's own holdings or totals: each present
 * holder becomes a group of their own — a child's land reads like a
 * portfolio under the parent. What is shown beyond the parcel itself follows
 * the administrator's switches in PortalSetting.
 */
class LinkedParcelsService
{
    /**
     * @return list<array{holder_id: int, holder: string, parcels_count: int, area: float, value: float|null, parcels: list<array<string, mixed>>}>
     */
    public function groups(Owner $owner, bool $forStaff = false): array
    {
        // The switch governs what the owner sees in the portal; staff always see values.
        $showValue = $forStaff || PortalSetting::allows(PortalSetting::SHOW_VALUE);

        return $this->query($owner)->get()
            ->map(fn (Parcel $parcel): array => $this->row($parcel, $showValue))
            ->groupBy('holder_id')
            ->map(fn (Collection $rows): array => [
                'holder_id' => (int) $rows->first()['holder_id'],
                'holder' => (string) $rows->first()['holder'],
                'parcels_count' => $rows->count(),
                'area' => (float) $rows->sum('area'),
                'value' => $showValue ? (float) $rows->sum('value') : null,
                'parcels' => $rows->values()->all(),
            ])
            ->sortByDesc('parcels_count')
            ->values()
            ->all();
    }

    /**
     * What the linked parcels add to the parent owner's headline figures: the
     * count and area always, the value only where it may be shown.
     *
     * @return array{parcels: int, area: float, value: float|null}
     */
    public function totals(Owner $owner, bool $forStaff = false): array
    {
        $groups = $this->groups($owner, $forStaff);
        $values = array_filter(array_column($groups, 'value'), static fn ($v): bool => $v !== null);

        return [
            'parcels' => (int) array_sum(array_column($groups, 'parcels_count')),
            'area' => (float) array_sum(array_column($groups, 'area')),
            'value' => $values === [] ? null : (float) array_sum($values),
        ];
    }

    public function has(Owner $owner): bool
    {
        return $owner->linkedParcels()->exists();
    }

    /** One linked parcel with its geometry, or a 404 — a parcel not under this owner does not exist to them. */
    public function find(Owner $owner, int $parcelId): Parcel
    {
        /** @var Parcel $parcel */
        $parcel = $this->query($owner)
            ->with(['boundary.engineeringOffice', 'photos'])
            ->selectRaw('ST_AsGeoJSON(parcels.geom, 6) AS geom_json')
            ->findOrFail($parcelId);

        return $parcel;
    }

    /**
     * Only with the documents switch on, and never the scan of a deed other
     * than the one the parcel is held under now.
     */
    public function canSeeDocument(Owner $owner, ParcelPhoto $photo): bool
    {
        if (! PortalSetting::allows(PortalSetting::SHOW_DOCUMENTS)) {
            return false;
        }

        $parcel = $owner->linkedParcels()->with('heldDeed')->whereKey($photo->parcel_id)->first();

        return $parcel !== null && ($photo->deed_id === null || $photo->deed_id === $parcel->heldDeed?->id);
    }

    /** @return Collection<int, ParcelPhoto> the documents a parent owner may open, empty when the switch is off */
    public function documents(Parcel $parcel): Collection
    {
        if (! PortalSetting::allows(PortalSetting::SHOW_DOCUMENTS)) {
            return new Collection;
        }

        $heldDeedId = $parcel->heldDeed?->id;

        return $parcel->photos->filter(fn (ParcelPhoto $photo): bool => $photo->deed_id === null || $photo->deed_id === $heldDeedId)->values();
    }

    /** @return Builder<Parcel> */
    private function query(Owner $owner): Builder
    {
        return $owner->linkedParcels()
            ->select('parcels.*')
            ->with(['plan.district.city', 'heldDeed.owners'])
            ->orderBy('parcels.parcel_no');
    }

    /** @return array<string, mixed> */
    private function row(Parcel $parcel, bool $showValue): array
    {
        $deed = $parcel->heldDeed;
        $holder = $deed?->owners->first();
        $area = $deed?->deed_area === null ? 0.0 : (float) $deed->deed_area;
        $value = $parcel->parcel_price ?? ($parcel->m_price === null ? null : (float) $parcel->m_price * $area);

        return [
            'id' => $parcel->id,
            'parcel_no' => $parcel->parcel_no,
            'plan_no' => $parcel->plan?->plan_no,
            'district' => $parcel->plan?->district?->name_ar,
            'city' => $parcel->plan?->district?->city?->name_ar,
            'asset_type' => $parcel->asset_type,
            'area' => $area,
            'value' => $showValue && $value !== null ? (float) $value : null,
            // A parcel between deeds has no holder on record; it is grouped under 0.
            'holder_id' => $holder === null ? 0 : $holder->id,
            'holder' => $deed?->owners->pluck('name')->implode('، ') ?: __('portal.linked_no_holder'),
        ];
    }
}
