<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Owner;
use App\Models\OwnerPortfolio;
use App\Models\Parcel;
use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates portfolios and moves parcels between them.
 *
 * A parcel sits in at most one portfolio per owner: assigning it to a new one
 * is an upsert on owner_portfolio_parcels, never a second row, which is what
 * the table's unique(owner_id, parcel_id) constraint enforces.
 */
class OwnerPortfolioService
{
    public function create(Owner $owner, string $name): OwnerPortfolio
    {
        return $owner->portfolios()->create(['name' => trim($name)]);
    }

    public function rename(OwnerPortfolio $portfolio, string $name): void
    {
        $portfolio->update(['name' => trim($name)]);
    }

    /** Deleting a portfolio unassigns its parcels; it never deletes the parcels themselves. */
    public function delete(OwnerPortfolio $portfolio): void
    {
        $portfolio->delete();
    }

    /** @throws InvalidArgumentException if the parcel is not held by this owner */
    public function assign(Owner $owner, Parcel $parcel, OwnerPortfolio $portfolio): void
    {
        $this->assertHeldBy($owner, $parcel);

        DB::table('owner_portfolio_parcels')->updateOrInsert(
            ['owner_id' => $owner->id, 'parcel_id' => $parcel->id],
            ['owner_portfolio_id' => $portfolio->id, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function unassign(Owner $owner, Parcel $parcel): void
    {
        DB::table('owner_portfolio_parcels')
            ->where('owner_id', $owner->id)
            ->where('parcel_id', $parcel->id)
            ->delete();
    }

    /**
     * Every parcel the owner holds, with the portfolio it currently sits in
     * (or null). One row per parcel — the view driving assignment needs the
     * full list, not just the assigned ones.
     *
     * @return list<array{parcel: Parcel, portfolio_id: int|null}>
     */
    public function parcelsWithAssignment(Owner $owner): array
    {
        $assignments = DB::table('owner_portfolio_parcels')
            ->where('owner_id', $owner->id)
            ->pluck('owner_portfolio_id', 'parcel_id');

        return $owner->parcels()->with('plan.district')->get()
            ->map(fn (Parcel $parcel): array => [
                'parcel' => $parcel,
                'portfolio_id' => $assignments->has($parcel->id) ? (int) $assignments->get($parcel->id) : null,
            ])
            ->all();
    }

    /**
     * Parcel count, live-computed area, and value for one portfolio. Value
     * only counts parcels that actually carry a price, same rule as the
     * dashboard's city portfolios.
     *
     * @return array{parcels: int, area: float, priced: int, value: float|null}
     */
    public function summary(OwnerPortfolio $portfolio): array
    {
        $ids = DB::table('owner_portfolio_parcels')
            ->where('owner_portfolio_id', $portfolio->id)
            ->pluck('parcel_id')
            ->all();

        return $this->figures($ids, (int) $portfolio->owner_id);
    }

    /**
     * The owner's held parcels that sit in none of their portfolios, with the
     * same figures as summary() — so an owner who never split their land, or
     * split only part of it, still sees all of it as portfolios.
     *
     * @return array{parcels: int, area: float, priced: int, value: float|null}
     */
    public function unassignedSummary(Owner $owner): array
    {
        $assigned = DB::table('owner_portfolio_parcels')->where('owner_id', $owner->id)->pluck('parcel_id')->all();
        $ids = $owner->parcels()->whereNotIn('parcels.id', $assigned)->pluck('parcels.id')->all();

        return $this->figures($ids, (int) $owner->id);
    }

    /**
     * Count, live-computed area and value of the given parcels, each area and
     * value taken at the owner's share of the parcel. Only parcels with a
     * shape count, as before.
     *
     * @param  list<int|string>  $ids
     * @return array{parcels: int, area: float, priced: int, value: float|null}
     */
    private function figures(array $ids, int $ownerId): array
    {
        $parcels = $ids === [] ? collect() : Parcel::query()
            ->whereKey($ids)
            ->whereNotNull('parcels.geom')
            ->with(OwnerHoldings::RELATIONS)
            ->select(['parcels.id', 'parcels.m_price'])
            ->selectRaw(Spatial::areaSqm('parcels.geom').' AS area_sqm')
            ->get();

        $holdings = app(OwnerHoldings::class);
        $area = 0.0;
        $value = null;
        $priced = 0;

        foreach ($parcels as $parcel) {
            $share = $holdings->share($parcel, $ownerId);
            $parcelArea = (float) $parcel->getAttribute('area_sqm');
            $area += $parcelArea * $share;

            if ($parcel->m_price !== null) {
                $priced++;
                $value = ($value ?? 0.0) + (float) $parcel->m_price * $parcelArea * $share;
            }
        }

        return [
            'parcels' => $parcels->count(),
            'area' => round($area, 2),
            'priced' => $priced,
            'value' => $value === null ? null : round($value, 2),
        ];
    }

    private function assertHeldBy(Owner $owner, Parcel $parcel): void
    {
        if (! $owner->parcels()->whereKey($parcel->id)->exists()) {
            throw new InvalidArgumentException('Parcel is not held by this owner.');
        }
    }
}
