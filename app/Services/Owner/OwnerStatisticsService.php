<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the figures the mobile app shows for one owner.
 *
 * Kept out of the controllers so the home screen and the profile screen read
 * the same numbers from one implementation.
 */
class OwnerStatisticsService
{
    /** @var Collection<int, int>|null */
    private ?Collection $parcelIds = null;

    public function __construct(private readonly Owner $owner) {}

    /**
     * Headline counters for the home screen.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $parcelIds = $this->parcelIds();
        $deedCounts = $this->deedCountsByStatus();
        $parcelsWithDeed = Deed::whereIn('parcel_id', $parcelIds)->distinct()->count('parcel_id');

        return [
            'parcels_total' => $parcelIds->count(),
            'deeds_active' => (int) ($deedCounts[DeedStatus::Updated->value] ?? 0),
            'deeds_expired' => (int) ($deedCounts[DeedStatus::Old->value] ?? 0),
            'parcels_without_deed' => max(0, $parcelIds->count() - $parcelsWithDeed),
            'survey_decisions_total' => $this->countIn('survey_decisions'),
            'area_total_sqm' => round((float) $this->ownedDeeds()->sum('deed_area'), 2),
            'cities_count' => count($this->byCity()),
            'documents_count' => $this->countIn('parcel_photos'),
        ];
    }

    /** Compact counters for the profile screen. */
    public function profileStats(): array
    {
        return [
            'parcels_count' => $this->parcelIds()->count(),
            'deeds_count' => DB::table('deed_owners')
                ->where('owner_id', $this->owner->getKey())
                ->distinct()
                ->count('deed_id'),
            'documents_count' => $this->countIn('parcel_photos'),
        ];
    }

    /** @return list<array{name: string, parcels_count: int}> */
    public function byCity(): array
    {
        return $this->groupedBy('cities');
    }

    /** @return list<array{name: string, parcels_count: int}> */
    public function byDistrict(): array
    {
        return $this->groupedBy('districts');
    }

    /** @return Collection<int, int> */
    private function parcelIds(): Collection
    {
        return $this->parcelIds ??= $this->owner->parcels()->pluck('parcels.id');
    }

    /** @return Builder<Deed> */
    private function ownedDeeds(): Builder
    {
        return Deed::whereHas(
            'owners',
            fn (Builder $query) => $query->whereKey($this->owner->getKey())
        );
    }

    /** @return Collection<string, int> */
    private function deedCountsByStatus(): Collection
    {
        return $this->ownedDeeds()
            ->selectRaw('deed_status, COUNT(*) AS total')
            ->groupBy('deed_status')
            ->pluck('total', 'deed_status');
    }

    private function countIn(string $table): int
    {
        return DB::table($table)->whereIn('parcel_id', $this->parcelIds())->count();
    }

    /**
     * Parcel counts grouped by city or district.
     *
     * @return list<array{name: string, parcels_count: int}>
     */
    private function groupedBy(string $level): array
    {
        $query = DB::table('parcels')
            ->join('plans', 'plans.id', '=', 'parcels.plan_id')
            ->join('districts', 'districts.id', '=', 'plans.district_id')
            ->whereIn('parcels.id', $this->parcelIds());

        if ($level === 'cities') {
            $query->join('cities', 'cities.id', '=', 'districts.city_id');
        }

        return $query
            ->selectRaw("{$level}.name_ar AS name, COUNT(parcels.id) AS parcels_count")
            ->groupBy("{$level}.name_ar")
            ->orderByDesc('parcels_count')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'parcels_count' => (int) $row->parcels_count,
            ])
            ->all();
    }
}
