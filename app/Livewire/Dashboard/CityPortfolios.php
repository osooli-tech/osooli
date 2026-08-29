<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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
        /** @var list<\stdClass> $rows */
        $rows = DB::select(
            "SELECT
                 COALESCE(c.name_ar, 'غير محدد') AS name,
                 COUNT(p.id) AS parcels,
                 COALESCE(SUM(ST_Area(p.geom::geography)), 0) AS area,
                 COUNT(p.m_price) AS priced,
                 SUM(p.m_price * ST_Area(p.geom::geography)) AS value
             FROM parcels p
             LEFT JOIN plans pl ON pl.id = p.plan_id
             LEFT JOIN districts d ON d.id = pl.district_id
             LEFT JOIN cities c ON c.id = d.city_id
             WHERE p.geom IS NOT NULL
             GROUP BY c.name_ar
             ORDER BY parcels DESC"
        );

        $this->portfolios = array_map(static fn (\stdClass $r): array => [
            'name' => (string) $r->name,
            'parcels' => (int) $r->parcels,
            'area' => round((float) $r->area, 2),
            'priced' => (int) $r->priced,
            'value' => $r->value === null ? null : round((float) $r->value, 2),
        ], $rows);
    }

    public function render(): View
    {
        return view('livewire.dashboard.city-portfolios');
    }
}
