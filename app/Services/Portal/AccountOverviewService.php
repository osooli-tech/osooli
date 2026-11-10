<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Parcel;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Services\Owner\OwnerHoldings;
use Illuminate\Support\Collection;

/**
 * Everything one portal account opens, across its owners: a card per owner
 * with what that owner holds at their share, and totals in which a parcel
 * shared by several of the account's owners counts once.
 *
 * Area is the area of the deed in force; value is the parcel's price, or the
 * price per metre times that area — the same reading as a parent owner's
 * linked parcels (LinkedParcelsService).
 */
class AccountOverviewService
{
    public function __construct(private readonly OwnerHoldings $holdings) {}

    /**
     * @return array{
     *     totals: array{parcels: int, area: float, value: float|null},
     *     owners: list<array{owner_id: int, name: string, relation: string, can_manage: bool, parcels: int, area: float, value: float|null,
     *         items: list<array{id: int, parcel_no: string|null, plan_no: string|null, district: string|null, city: string|null, share: float, area: float, value: float|null}>}>
     * }
     */
    public function for(PortalAccount $account, bool $showValue): array
    {
        $grants = $this->grants($account);
        $parcels = $this->parcels($grants->pluck('owner_id')->all());

        $owners = $grants->map(function (PortalAccountOwner $grant) use ($parcels, $showValue): array {
            $items = [];

            foreach ($parcels as $row) {
                $share = $row['shares'][$grant->owner_id] ?? null;

                if ($share === null) {
                    continue;
                }

                $items[] = [
                    'id' => $row['parcel']->id,
                    'parcel_no' => $row['parcel']->parcel_no,
                    'plan_no' => $row['parcel']->plan?->plan_no,
                    'district' => $row['parcel']->plan?->district?->name_ar,
                    'city' => $row['parcel']->plan?->district?->city?->name_ar,
                    'share' => $share,
                    'area' => $row['area'] * $share,
                    'value' => $showValue && $row['value'] !== null ? $row['value'] * $share : null,
                ];
            }

            $priced = array_filter($items, static fn (array $item): bool => $item['value'] !== null);

            return [
                'owner_id' => (int) $grant->owner_id,
                'name' => (string) $grant->owner?->name,
                'relation' => $grant->relation->value,
                'can_manage' => $grant->can_manage,
                'parcels' => count($items),
                'area' => (float) array_sum(array_column($items, 'area')),
                'value' => $priced === [] ? null : (float) array_sum(array_column($priced, 'value')),
                'items' => $items,
            ];
        })->values()->all();

        // Each parcel once, at the part the account's owners hold together.
        $area = 0.0;
        $value = null;

        foreach ($parcels as $row) {
            $held = array_sum($row['shares']);
            $area += $row['area'] * $held;

            if ($showValue && $row['value'] !== null) {
                $value = ($value ?? 0.0) + $row['value'] * $held;
            }
        }

        return [
            'totals' => ['parcels' => $parcels->count(), 'area' => $area, 'value' => $value],
            'owners' => $owners,
        ];
    }

    /**
     * Every parcel the account's owners hold, once, for the overview map.
     * Each carries the account's owners on it, the first deciding its colour.
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function map(PortalAccount $account): array
    {
        $grants = $this->grants($account);
        $order = array_flip($grants->pluck('owner_id')->map(fn ($id): int => (int) $id)->all());
        $names = $grants->mapWithKeys(fn (PortalAccountOwner $g): array => [(int) $g->owner_id => (string) $g->owner?->name])->all();

        $features = [];

        foreach ($this->parcels(array_keys($order), withShape: true) as $row) {
            $geometry = $row['parcel']->getAttribute('geom_json');

            if ($geometry === null) {
                continue;
            }

            $ownerIds = array_keys($row['shares']);
            usort($ownerIds, static fn (int $a, int $b): int => $order[$a] <=> $order[$b]);

            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode((string) $geometry, false),
                'properties' => [
                    'id' => $row['parcel']->id,
                    'parcel_no' => $row['parcel']->parcel_no,
                    'plan_no' => $row['parcel']->plan?->plan_no,
                    'district' => $row['parcel']->plan?->district?->name_ar,
                    'owner_ids' => $ownerIds,
                    'owner_names' => array_map(static fn (int $id): string => $names[$id] ?? '', $ownerIds),
                    'color_index' => $order[$ownerIds[0]] ?? 0,
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /** @return Collection<int, PortalAccountOwner> */
    private function grants(PortalAccount $account): Collection
    {
        return $account->usableGrants()->with('owner:id,name')->orderBy('id')->get()->toBase();
    }

    /**
     * The parcels held today by any of the owners, with each one's share,
     * keeping only the account's owners.
     *
     * @param  list<int|string>  $ownerIds
     * @return Collection<int, array{parcel: Parcel, shares: array<int, float>, area: float, value: float|null}>
     */
    private function parcels(array $ownerIds, bool $withShape = false): Collection
    {
        if ($ownerIds === []) {
            return collect();
        }

        $wanted = array_flip(array_map('intval', $ownerIds));

        $query = Parcel::query()
            ->select('parcels.*')
            ->whereHas('heldDeed.deedOwners', fn ($q) => $q->whereIn('owner_id', $ownerIds))
            ->with([...OwnerHoldings::RELATIONS, 'plan.district.city'])
            ->orderBy('parcels.parcel_no');

        if ($withShape) {
            $query->whereNotNull('parcels.geom')->selectRaw('ST_AsGeoJSON(parcels.geom, 6) AS geom_json');
        }

        $rows = [];

        foreach ($query->get() as $parcel) {
            $area = (float) ($parcel->heldDeed->deed_area ?? 0);
            $value = $parcel->parcel_price !== null
                ? (float) $parcel->parcel_price
                : ($parcel->m_price === null ? null : (float) $parcel->m_price * $area);

            $rows[] = [
                'parcel' => $parcel,
                'shares' => array_intersect_key($this->holdings->shares($parcel), $wanted),
                'area' => $area,
                'value' => $value,
            ];
        }

        return collect($rows);
    }
}
