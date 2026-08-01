<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Owner;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds parcel queries that can only ever reach one owner's parcels.
 *
 * Every mobile read starts here, so the ownership rule lives in one place
 * instead of being repeated — and forgotten — in each controller.
 */
class OwnerParcelQuery
{
    public function __construct(private readonly Owner $owner) {}

    /**
     * Owner-scoped parcels, with the PostGIS values the API exposes.
     *
     * @return Builder<Parcel>
     */
    public function base(): Builder
    {
        return $this->owner->parcels()
            ->select('parcels.*')
            ->selectRaw('ST_AsGeoJSON(parcels.geom, 6) AS geom_json')
            ->selectRaw('ST_Y(ST_Centroid(parcels.geom)) AS centroid_lat')
            ->selectRaw('ST_X(ST_Centroid(parcels.geom)) AS centroid_lng');
    }

    /**
     * Applies the list screen's filters.
     *
     * @param  array{search?: string|null, asset_type?: string|null, city?: string|null, district?: string|null}  $filters
     * @return Builder<Parcel>
     */
    public function filtered(array $filters): Builder
    {
        return $this->base()
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $this->applySearch($query, (string) $filters['search'])
            )
            ->when(
                filled($filters['asset_type'] ?? null),
                fn (Builder $query) => $query->where('asset_type', $filters['asset_type'])
            )
            ->when(
                filled($filters['city'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'plan.district.city',
                    fn (Builder $city) => $city->where('name_ar', $filters['city'])
                )
            )
            ->when(
                filled($filters['district'] ?? null),
                fn (Builder $query) => $query->whereHas(
                    'plan.district',
                    fn (Builder $district) => $district->where('name_ar', $filters['district'])
                )
            );
    }

    /**
     * Narrows to one search axis, used by the search screen's tabs.
     *
     * @return Builder<Parcel>
     */
    public function searchBy(string $type, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $this->base()->where(fn (Builder $query) => match ($type) {
            'parcel' => $query->where('parcel_no', 'ilike', $like),
            'plan' => $query->whereHas('plan', fn (Builder $plan) => $plan->where('plan_no', 'ilike', $like)),
            // Still owner-scoped by base(), so this never surfaces other owners.
            'owner' => $query->whereHas('deeds.owners', fn (Builder $owner) => $owner->where('name', 'ilike', $like)),
            default => $query->whereHas('deeds', fn (Builder $deed) => $deed->where('deed_no', 'ilike', $like)),
        });
    }

    /** @return Builder<Parcel> */
    public function withGeometry(): Builder
    {
        return $this->base()->whereNotNull('parcels.geom');
    }

    /** @param  Builder<Parcel>  $query */
    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('parcel_no', 'ilike', $like)
                ->orWhereHas('deeds', fn (Builder $deed) => $deed->where('deed_no', 'ilike', $like))
                ->orWhereHas('plan', fn (Builder $plan) => $plan->where('plan_no', 'ilike', $like));
        });
    }
}
