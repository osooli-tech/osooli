<?php

declare(strict_types=1);

namespace App\Livewire\Reference;

use App\Livewire\Concerns\AppliesPlacementFix;
use App\Models\City;
use App\Models\District;
use App\Models\Plan;
use App\Support\Geo\MissingBoundaries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * «الحدود الناقصة» on the reference screen: every district, city and region
 * with no boundary, with the one thing to do for each — draw a district
 * from its parcels, or move a record's children to the bounded one its
 * land already belongs to (MissingBoundaries).
 */
class MissingBoundariesPanel extends Component
{
    use AppliesPlacementFix;

    public bool $show = false;

    /** @var array<string, list<array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null, unlocated: int}>> */
    public array $report = [];

    #[On('missing-boundaries')]
    public function open(): void
    {
        $this->authorizeDraw();

        $this->report = MissingBoundaries::report();
        $this->show = true;
    }

    public function close(): void
    {
        $this->reset(['show', 'report']);
    }

    public function draw(int $districtId): void
    {
        $this->authorizeDraw();

        $drawn = $this->drawOne($districtId);
        $this->dispatch('toast', type: $drawn ? 'success' : 'error', message: $drawn ? __('boundaries.missing.drawn', ['count' => 1]) : __('placement.fix.stale'));
        $this->refresh();
    }

    public function drawAll(): void
    {
        $this->authorizeDraw();
        @set_time_limit(0);

        $drawn = 0;
        foreach (MissingBoundaries::report()['districts'] as $row) {
            if ($row['action'] === 'draw' && $this->drawOne($row['id'])) {
                $drawn++;
            }
        }

        $this->dispatch('toast', type: 'success', message: __('boundaries.missing.drawn', ['count' => $drawn]));
        $this->refresh();
    }

    /** Moves a record's children to where its land already belongs. */
    public function move(string $level, int $id): void
    {
        $this->authorizePlacementFix();

        $row = MissingBoundaries::row($level, $id);
        if ($row === null || ! $this->moveRow($level, $row)) {
            $this->dispatch('toast', type: 'error', message: __('placement.fix.stale'));
            $this->refresh();

            return;
        }

        $this->dispatch('toast', type: 'success', message: __('boundaries.missing.moved.'.$level, ['name' => $row['name'], 'target' => $row['target']]));
        $this->refresh();
    }

    /**
     * Everything the report can settle, in order: regions and cities into
     * the National Address ones, districts into their city (or the bounded
     * district they are), then every district drawn from its parcels.
     */
    public function solveAll(): void
    {
        $this->authorizeDraw();
        $this->authorizePlacementFix();
        @set_time_limit(0);

        $moved = 0;
        foreach (['regions', 'cities', 'districts'] as $level) {
            foreach (MissingBoundaries::report()[$level] as $row) {
                if ($this->moveRow($level, $row)) {
                    $moved++;
                }
            }
        }

        $this->dispatch('toast', type: 'success', message: __('boundaries.missing.solved', ['moved' => $moved]));
        $this->refresh();
    }

    /**
     * Takes off every boundary drawn from parcels: they say where the parcels
     * are, not where the district is, so the check falls back to the
     * official boundary of the city or village again.
     */
    public function clearDrawn(): void
    {
        $this->authorizeDraw();

        $ids = DB::table('districts')->where('boundary_source', 'parcels')->pluck('id');
        foreach ($ids as $id) {
            $this->writeSafely('district.geometry_remove', 'district', (int) $id, fn (): int => DB::table('districts')->where('id', $id)
                ->update(['geom' => null, 'boundary_source' => null, 'updated_at' => now()]));
        }

        $this->dispatch('toast', type: 'success', message: __('boundaries.missing.cleared', ['count' => $ids->count()]));
        $this->refresh();
    }

    /** A district no plan uses: nothing depends on it, so it can go. */
    public function delete(int $districtId): void
    {
        abort_unless(Auth::user()?->can('reference.delete'), 403);

        if ((MissingBoundaries::row('districts', $districtId)['action'] ?? null) !== 'unused') {
            $this->dispatch('toast', type: 'error', message: __('placement.fix.stale'));
            $this->refresh();

            return;
        }

        // Its empty plans go with it — stand-ins like «بدون - بطين-1» left
        // behind when their parcels moved to where they lie.
        $this->writeSafely('district.delete', 'district', $districtId, function () use ($districtId): mixed {
            Plan::query()->where('district_id', $districtId)->delete();

            return District::query()->whereKey($districtId)->delete();
        });
        $this->dispatch('toast', type: 'success', message: __('common.deleted'));
        $this->refresh();
    }

    /**
     * @param  array{id: int, name: string, parent: string, parcels: int, action: string, target_id: int|null, target: string|null, unlocated: int}  $row
     */
    private function moveRow(string $level, array $row): bool
    {
        $id = $row['id'];
        $target = $row['target_id'];
        if ($target === null || ! in_array($row['action'], ['merge', 'move'], true)) {
            return false;
        }

        match ($level.'.'.$row['action']) {
            'districts.merge' => $this->writeSafely('district.merge', 'district', $id, fn (): int => Plan::query()->where('district_id', $id)->update(['district_id' => $target])),
            'districts.move' => $this->writeSafely('district.reassign', 'district', $id, fn (): int => District::query()->whereKey($id)->update(['city_id' => $target])),
            'cities.merge' => $this->writeSafely('city.merge', 'city', $id, fn (): int => District::query()->where('city_id', $id)->update(['city_id' => $target])),
            'regions.merge' => $this->writeSafely('region.merge', 'region', $id, fn (): int => City::query()->where('region_id', $id)->update(['region_id' => $target])),
            default => null,
        };

        return true;
    }

    public function render(): View
    {
        return view('livewire.reference.missing-boundaries-panel', [
            'canMove' => $this->mayFixPlacement(),
            'canDelete' => Auth::user()?->can('reference.delete') === true,
            'drawnCount' => DB::table('districts')->where('boundary_source', 'parcels')->count(),
        ]);
    }

    private function drawOne(int $districtId): bool
    {
        // Drawn and recorded together; nothing is recorded for a district
        // that turned out not to be drawable any more.
        return (bool) DB::transaction(function () use ($districtId): bool {
            if (! MissingBoundaries::drawDistrict($districtId)) {
                return false;
            }

            return $this->writeSafely('district.geometry_derive', 'district', $districtId, fn (): bool => true);
        });
    }

    private function refresh(): void
    {
        $this->report = MissingBoundaries::report();
        $this->dispatch('boundary-saved');
    }

    private function authorizeDraw(): void
    {
        abort_unless(Auth::user()?->can('boundaries.edit'), 403);
    }
}
