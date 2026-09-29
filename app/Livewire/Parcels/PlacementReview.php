<?php

declare(strict_types=1);

namespace App\Livewire\Parcels;

use App\Livewire\Concerns\AppliesPlacementFix;
use App\Support\Geo\ParcelPlacement;
use App\Support\Geo\PlacementFix;
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
 * the data can be checked in one sitting instead of one save at a time —
 * and, with the permission, corrected from here (PlacementFix).
 */
class PlacementReview extends Component
{
    use AppliesPlacementFix;
    use WithPagination;

    private const PER_PAGE = 20;

    /** 'district' or 'city' — which boundary the parcels are outside of. */
    public string $level = 'district';

    /** The parcel whose reassign dialog is open. */
    public ?int $fixing = null;

    /** @var list<array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}> */
    public array $fixOptions = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('parcels.placement'), 403);
    }

    public function show(string $level): void
    {
        abort_unless(in_array($level, ['district', 'city'], true), 404);
        $this->level = $level;
        $this->resetPage();
    }

    public function openFix(int $parcelId): void
    {
        $this->authorizePlacementFix();

        $this->fixing = $parcelId;
        $this->fixOptions = PlacementFix::options($parcelId);
    }

    public function closeFix(): void
    {
        $this->fixing = null;
        $this->fixOptions = [];
    }

    /**
     * Move the plan to the district, or the district to the city, the
     * parcel actually lies in — for every parcel sharing it.
     */
    public function applyFix(string $kind, int $targetId): void
    {
        $this->authorizePlacementFix();

        // Worked out again now: the dialog may have sat open while someone
        // else moved the same plan.
        $this->applyPlacementOption($this->fixing === null ? null : PlacementFix::find($this->fixing, $kind, $targetId));
        $this->closeFix();
    }

    /**
     * Every listed parcel with no real plan, moved to the official district
     * (or village) it lies in. Parcels of real plans are left for a decision.
     */
    public function relocateStandIns(): void
    {
        $this->authorizePlacementFix();
        @set_time_limit(0);

        $moved = 0;
        foreach (['district', 'city'] as $level) {
            foreach ($this->outside($level)->pluck('p.id') as $id) {
                $option = PlacementFix::options((int) $id)[0] ?? null;
                if ($option !== null && in_array($option['kind'], ['parcel', 'parcel_city'], true)) {
                    $this->writePlacementOption($option);
                    $moved++;
                }
            }
        }

        $this->dispatch('toast', type: 'success', message: __('placement.fix.relocated', ['count' => $moved]));
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
            'canFix' => $this->mayFixPlacement(),
            'standIns' => $this->mayFixPlacement() ? $this->standInCount() : 0,
        ]);
    }

    /** How many listed parcels have no real plan («بدون …»). */
    private function standInCount(): int
    {
        return $this->outside('district')->where('pl.plan_no', 'like', 'بدون%')->count()
            + $this->outside('city')->where('pl.plan_no', 'like', 'بدون%')->count();
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
