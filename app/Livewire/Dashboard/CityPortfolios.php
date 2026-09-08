<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Parcel;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Every parcel in a city is treated as part of that city's portfolio, so the
 * grouping is derived rather than stored — nothing to maintain, and it stays
 * correct as parcels are synced.
 *
 * Area comes from PostGIS rather than the deed, because a city total should
 * reflect the ground, and value only counts the parcels that carry a price.
 */
class CityPortfolios extends Component
{
    /** @var list<array{name: string, parcels: int, area: float, value: float|null, priced: int}> */
    public array $portfolios = [];

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $parcelIds = OwnerScope::parcelIds($user);

        $rows = Parcel::query()
            ->leftJoin('plans', 'plans.id', '=', 'parcels.plan_id')
            ->leftJoin('districts', 'districts.id', '=', 'plans.district_id')
            ->leftJoin('cities', 'cities.id', '=', 'districts.city_id')
            ->whereNotNull('parcels.geom')
            ->when($parcelIds !== null, fn ($q) => $q->whereIn('parcels.id', $parcelIds))
            ->selectRaw("
                COALESCE(cities.name_ar, 'غير محدد') AS name,
                COUNT(parcels.id) AS parcels,
                COALESCE(SUM(ST_Area(parcels.geom::geography)), 0) AS area,
                COUNT(parcels.m_price) AS priced,
                SUM(parcels.m_price * ST_Area(parcels.geom::geography)) AS value
            ")
            ->groupBy('cities.name_ar')
            ->orderByDesc('parcels')
            ->get();

        $this->portfolios = $rows->map(static fn (Parcel $r): array => [
            'name' => (string) $r->getAttribute('name'),
            'parcels' => (int) $r->getAttribute('parcels'),
            'area' => round((float) $r->getAttribute('area'), 2),
            'priced' => (int) $r->getAttribute('priced'),
            'value' => $r->getAttribute('value') === null ? null : round((float) $r->getAttribute('value'), 2),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.dashboard.city-portfolios');
    }
}
