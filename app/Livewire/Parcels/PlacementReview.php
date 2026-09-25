<?php

declare(strict_types=1);

namespace App\Livewire\Parcels;

use App\Support\Geo\ParcelPlacement;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every parcel whose polygon lies outside the place its plan says it is in:
 * outside the plan's district where the district has a boundary, otherwise
 * outside the district's city. Each is listed with where it actually is, so
 * the data can be checked in one sitting instead of one save at a time.
 */
class PlacementReview extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    /** 'district' or 'city' — which boundary the parcels are outside of. */
    public string $level = 'district';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('parcels.view'), 403);
    }

    public function show(string $level): void
    {
        abort_unless(in_array($level, ['district', 'city'], true), 404);
        $this->level = $level;
        $this->resetPage();
    }

    public function render(): View
    {
        $page = $this->outside($this->level)->orderBy('p.id')->paginate(self::PER_PAGE);

        $rows = collect($page->items())->map(fn (object $row): array => [
            'row' => $row,
            'actual' => ParcelPlacement::forParcel((int) $row->id)['actual'],
        ]);

        return view('livewire.parcels.placement-review', [
            'page' => $page,
            'rows' => $rows,
            'counts' => [
                'district' => $this->outside('district')->count(),
                'city' => $this->outside('city')->count(),
            ],
        ]);
    }

    /** Live parcels with a polygon, outside the boundary of their plan's district (or city). */
    private function outside(string $level): Builder
    {
        $surface = 'ST_PointOnSurface(p.geom)';
        $query = DB::table('parcels as p')
            ->join('plans as pl', 'pl.id', '=', 'p.plan_id')
            ->join('districts as d', 'd.id', '=', 'pl.district_id')
            ->join('cities as c', 'c.id', '=', 'd.city_id')
            ->whereNotNull('p.geom')
            ->whereNull('p.deleted_at')
            ->select(['p.id', 'p.parcel_no', 'p.geo_id', 'pl.plan_no', 'd.name_ar as district', 'c.name_ar as city', 'c.boundary_source as city_source']);

        if ($level === 'district') {
            $query->whereNotNull('d.geom')->whereRaw("NOT ST_Contains(d.geom, {$surface})");
        } else {
            // Only where the district has no boundary of its own to decide by.
            $query->whereNull('d.geom')->whereNotNull('c.geom')->whereRaw("NOT ST_Contains(c.geom, {$surface})");
        }

        $visible = OwnerScope::parcelIds(Auth::user());
        if ($visible !== null) {
            $query->whereIn('p.id', $visible);
        }

        return $query;
    }
}
