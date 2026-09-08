<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Owner;
use App\Models\OwnerPortfolio;
use App\Models\Parcel;
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
        // Not $portfolio->parcels() here: a BelongsToMany relation always
        // appends its own pivot columns to the select list, which Postgres
        // then refuses next to a bare aggregate with no GROUP BY. A plain
        // join sidesteps that entirely.
        $row = Parcel::query()
            ->join('owner_portfolio_parcels', 'owner_portfolio_parcels.parcel_id', '=', 'parcels.id')
            ->where('owner_portfolio_parcels.owner_portfolio_id', $portfolio->id)
            ->whereNotNull('parcels.geom')
            ->selectRaw('
                COUNT(parcels.id) AS parcels,
                COALESCE(SUM(ST_Area(parcels.geom::geography)), 0) AS area,
                COUNT(parcels.m_price) AS priced,
                SUM(parcels.m_price * ST_Area(parcels.geom::geography)) AS value
            ')
            ->first();

        $value = $row?->getAttribute('value');

        return [
            'parcels' => (int) ($row?->getAttribute('parcels') ?? 0),
            'area' => round((float) ($row?->getAttribute('area') ?? 0), 2),
            'priced' => (int) ($row?->getAttribute('priced') ?? 0),
            'value' => $value === null ? null : round((float) $value, 2),
        ];
    }

    private function assertHeldBy(Owner $owner, Parcel $parcel): void
    {
        if (! $owner->parcels()->whereKey($parcel->id)->exists()) {
            throw new InvalidArgumentException('Parcel is not held by this owner.');
        }
    }
}
