<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds deed queries restricted to the deeds an owner actually holds.
 */
class OwnerDeedQuery
{
    /** Maps the app's status filter onto the stored deed_status values. */
    private const STATUS_MAP = [
        'active' => DeedStatus::Updated,
        'expired' => DeedStatus::Old,
    ];

    public function __construct(private readonly Owner $owner) {}

    /** @return Builder<Deed> */
    public function base(): Builder
    {
        return Deed::whereHas(
            'owners',
            fn (Builder $query) => $query->whereKey($this->owner->getKey())
        );
    }

    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return Builder<Deed>
     */
    public function filtered(array $filters): Builder
    {
        $status = self::STATUS_MAP[$filters['status'] ?? 'all'] ?? null;

        return $this->base()
            ->when($status !== null, fn (Builder $query) => $query->where('deed_status', $status->value))
            ->when(
                filled($filters['search'] ?? null),
                fn (Builder $query) => $this->applySearch($query, (string) $filters['search'])
            );
    }

    /** @param  Builder<Deed>  $query */
    private function applySearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('deed_no', 'ilike', $like)
                ->orWhereHas('parcel', fn (Builder $parcel) => $parcel->where('parcel_no', 'ilike', $like))
                ->orWhereHas('parcel.plan', fn (Builder $plan) => $plan->where('plan_no', 'ilike', $like))
                ->orWhereHas('parcel.plan.district', fn (Builder $district) => $district->where('name_ar', 'ilike', $like))
                ->orWhereHas('parcel.plan.district.city', fn (Builder $city) => $city->where('name_ar', 'ilike', $like));
        });
    }
}
