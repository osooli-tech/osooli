<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
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

    /**
     * Estimated value of the owner's holdings, weighted by ownership share.
     *
     * Prices come from the survey data source (parcels.parcel_price), so some
     * parcels may be unpriced; the counts let the app say how much of the
     * portfolio the total actually covers.
     *
     * @return array{total_value: float|null, priced_parcels: int, total_parcels: int, avg_m_price: float|null}
     */
    public function portfolio(): array
    {
        $parcels = $this->owner->parcels()
            ->with('latestDeed.deedOwners')
            ->get(['parcels.id', 'parcels.m_price', 'parcels.parcel_price']);

        $total = 0.0;
        $priced = 0;
        $mPrices = [];

        foreach ($parcels as $parcel) {
            if ($parcel->m_price !== null) {
                $mPrices[] = (float) $parcel->m_price;
            }

            if ($parcel->parcel_price === null) {
                continue;
            }

            $priced++;
            $total += (float) $parcel->parcel_price * $this->shareIn($parcel);
        }

        return [
            'total_value' => $priced > 0 ? round($total, 2) : null,
            'priced_parcels' => $priced,
            'total_parcels' => $parcels->count(),
            'avg_m_price' => $mPrices === [] ? null : round(array_sum($mPrices) / count($mPrices), 2),
        ];
    }

    /**
     * The owner's fraction (0..1) of one parcel, read from the latest deed.
     *
     * A NULL ownership_share means the share is only written as prose inside
     * the deed document, so co-owners are assumed to hold equal parts. Owners
     * reached through an older deed only are treated as full holders rather
     * than silently dropping the parcel from the total.
     */
    private function shareIn(Parcel $parcel): float
    {
        $deed = $parcel->latestDeed;

        if ($deed === null || $deed->deedOwners->isEmpty()) {
            return 1.0;
        }

        $mine = $deed->deedOwners->firstWhere('owner_id', $this->owner->getKey());

        if ($mine === null) {
            return 1.0;
        }

        if ($mine->ownership_share !== null) {
            return min(100.0, max(0.0, (float) $mine->ownership_share)) / 100;
        }

        return 1.0 / max(1, $deed->deedOwners->count());
    }

    /** Compact counters for the profile screen. */
    public function profileStats(): array
    {
        return [
            'parcels_count' => $this->parcelIds()->count(),
            // Joined to deeds only to drop archived ones: this counter reads
            // deed_owners directly, so it is the one place here the SoftDeletes
            // scope cannot reach. The other figures all narrow by parcelIds(),
            // which comes from an Eloquent relation and is already filtered.
            'deeds_count' => DB::table('deed_owners')
                ->join('deeds', 'deeds.id', '=', 'deed_owners.deed_id')
                ->where('deed_owners.owner_id', $this->owner->getKey())
                ->whereNull('deeds.deleted_at')
                ->distinct()
                ->count('deed_owners.deed_id'),
            'documents_count' => $this->countIn('parcel_photos'),
        ];
    }

    /**
     * Total/average/largest/smallest deed area — the same four-in-one
     * aggregate the internal dashboard's KPI cards compute, scoped here to
     * the owner's own deeds instead of every deed in the system.
     *
     * @return array{total: float, avg: float, max: float, min: float}
     */
    public function areaExtremes(): array
    {
        $row = $this->ownedDeeds()
            ->selectRaw('
                COALESCE(SUM(deed_area), 0) AS total,
                COALESCE(AVG(deed_area), 0) AS avg,
                COALESCE(MAX(deed_area), 0) AS max_v,
                COALESCE(MIN(deed_area), 0) AS min_v
            ')
            ->first();

        return [
            'total' => round((float) ($row?->getAttribute('total') ?? 0), 2),
            'avg' => round((float) ($row?->getAttribute('avg') ?? 0), 2),
            'max' => round((float) ($row?->getAttribute('max_v') ?? 0), 2),
            'min' => round((float) ($row?->getAttribute('min_v') ?? 0), 2),
        ];
    }

    /** Distinct plans the owner's parcels sit on. */
    public function plansCount(): int
    {
        return Parcel::query()
            ->whereIn('id', $this->parcelIds())
            ->distinct('plan_id')
            ->count('plan_id');
    }

    /** @return list<array{name: string, parcels_count: int}> */
    public function byAssetType(): array
    {
        return Parcel::query()
            ->whereIn('id', $this->parcelIds())
            ->whereNotNull('asset_type')
            ->selectRaw('asset_type AS name, COUNT(*) AS parcels_count')
            ->groupBy('asset_type')
            ->orderByDesc('parcels_count')
            ->get()
            ->map(fn (Parcel $row): array => [
                'name' => (string) $row->getAttribute('name'),
                'parcels_count' => (int) $row->getAttribute('parcels_count'),
            ])
            ->all();
    }

    /** @return list<array{name: string, parcels_count: int}> */
    public function byQrarSource(): array
    {
        return DB::table('survey_decisions')
            ->whereIn('parcel_id', $this->parcelIds())
            ->whereNotNull('qrar_source')
            ->selectRaw('qrar_source AS name, COUNT(*) AS parcels_count')
            ->groupBy('qrar_source')
            ->orderByDesc('parcels_count')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'parcels_count' => (int) $row->parcels_count,
            ])
            ->all();
    }

    /** @return list<array{name: string, parcels_count: int}> */
    public function byEngineeringOffice(): array
    {
        return DB::table('parcel_boundaries')
            ->join('engineering_offices', 'engineering_offices.id', '=', 'parcel_boundaries.engineering_office_id')
            ->whereIn('parcel_boundaries.parcel_id', $this->parcelIds())
            ->selectRaw('engineering_offices.name AS name, COUNT(*) AS parcels_count')
            ->groupBy('engineering_offices.name')
            ->orderByDesc('parcels_count')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'parcels_count' => (int) $row->parcels_count,
            ])
            ->all();
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

    /** @return list<array{name: string, parcels_count: int}> */
    public function byRegion(): array
    {
        return $this->groupedBy('regions');
    }

    /**
     * Parcel counts per recorded owner on the latest deed of each owned
     * parcel — shows how holdings split between the owner and co-owners.
     *
     * @return list<array{name: string, parcels_count: int}>
     */
    public function byOwner(): array
    {
        return DB::table('parcels')
            ->join('deeds', function ($join): void {
                $join->on('deeds.parcel_id', '=', 'parcels.id')
                    ->whereRaw('deeds.id = (SELECT MAX(d2.id) FROM deeds d2 WHERE d2.parcel_id = parcels.id)');
            })
            ->join('deed_owners', 'deed_owners.deed_id', '=', 'deeds.id')
            ->join('owners', 'owners.id', '=', 'deed_owners.owner_id')
            ->whereIn('parcels.id', $this->parcelIds())
            ->selectRaw('owners.name AS name, COUNT(DISTINCT parcels.id) AS parcels_count')
            ->groupBy('owners.name')
            ->orderByDesc('parcels_count')
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'parcels_count' => (int) $row->parcels_count,
            ])
            ->all();
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
     * Parcel counts grouped by city, district, or region.
     *
     * @return list<array{name: string, parcels_count: int}>
     */
    private function groupedBy(string $level): array
    {
        $query = DB::table('parcels')
            ->join('plans', 'plans.id', '=', 'parcels.plan_id')
            ->join('districts', 'districts.id', '=', 'plans.district_id')
            ->whereIn('parcels.id', $this->parcelIds());

        if ($level === 'cities' || $level === 'regions') {
            $query->join('cities', 'cities.id', '=', 'districts.city_id');
        }

        if ($level === 'regions') {
            $query->join('regions', 'regions.id', '=', 'cities.region_id');
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
