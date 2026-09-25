<?php

declare(strict_types=1);

namespace App\Livewire\Reference;

use App\Support\Concerns\WritesSafely;
use App\Support\Database\Dialect;
use App\Support\Database\Spatial;
use App\Support\ParcelGeometry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Draw or correct the boundary of a district, city, region or country by hand.
 *
 * The National Address boundaries are loaded by a seeder; where one is wrong
 * or missing — a new district, a city whose outline was only derived — it is
 * redrawn here. A boundary saved here is marked 'manual', and the seeder never
 * overwrites a manual boundary when it is run again.
 */
class BoundaryEditor extends Component
{
    use WritesSafely;

    /**
     * Table => [audit target, parent column, simplification for neighbours].
     * The neighbours are the other boundaries under the same parent, shown
     * faintly so a shared edge can be traced rather than guessed.
     */
    private const LEVELS = [
        'districts' => ['audit' => 'district', 'parent' => 'city_id', 'simplify' => 0.00002],
        'cities' => ['audit' => 'city', 'parent' => 'region_id', 'simplify' => 0.0002],
        'regions' => ['audit' => 'region', 'parent' => 'country_id', 'simplify' => 0.002],
        'countries' => ['audit' => 'country', 'parent' => null, 'simplify' => 0.002],
    ];

    /** Most sibling outlines worth drawing; a city can have hundreds of districts. */
    private const MAX_NEIGHBOURS = 400;

    public bool $show = false;

    #[Locked]
    public ?string $level = null;

    #[Locked]
    public ?int $recordId = null;

    /** `updated_at` as it stood when the editor opened. */
    #[Locked]
    public ?string $openedAt = null;

    #[On('boundary-edit')]
    public function open(string $level, int $id): void
    {
        $this->authorize($level);

        $row = DB::table($level)->where('id', $id)->first(['id', 'updated_at']);
        abort_if($row === null, 404);

        $this->level = $level;
        $this->recordId = $id;
        $this->openedAt = $row->updated_at === null ? null : (string) $row->updated_at;
        $this->resetErrorBag();
        $this->show = true;
    }

    public function save(string $geojson): void
    {
        [$level, $id] = $this->editing();
        $this->resetErrorBag();

        try {
            $checked = ParcelGeometry::validateBoundary($geojson);
        } catch (InvalidArgumentException $exception) {
            $this->addError('geometry', $exception->getMessage());

            return;
        }

        // Already a MultiPolygon, which is what both databases' columns hold.
        if ($this->write($level, $id, 'geometry_edit', 'geom = '.Spatial::fromGeoJson().", boundary_source = 'manual'", [$checked['geojson']])) {
            $this->finish(__('boundaries.saved'));
        }
    }

    /**
     * Take the boundary off altogether. Running the boundaries seeder again
     * brings back the National Address one, if there is one to bring back.
     */
    public function remove(): void
    {
        [$level, $id] = $this->editing();
        $this->resetErrorBag();

        if ($this->write($level, $id, 'geometry_remove', 'geom = NULL, boundary_source = NULL', [])) {
            $this->finish(__('boundaries.removed'));
        }
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $record = $this->show && $this->level !== null && $this->recordId !== null
            ? DB::table($this->level)->where('id', $this->recordId)->first(['id', 'name_ar', 'name_en', 'boundary_source'])
            : null;

        return view('livewire.reference.boundary-editor', [
            'record' => $record,
            'config' => $record === null ? null : [
                'token' => (string) config('services.mapbox.token'),
                'geometry' => $this->current((string) $this->level, (int) $this->recordId),
                'neighbours' => $this->neighbours((string) $this->level, (int) $this->recordId),
                'bounds' => $this->fallbackBounds((string) $this->level, (int) $this->recordId),
                'i18n' => [
                    'empty' => __('parcels.geometry_errors.empty'),
                    'paste_invalid' => __('parcels.geometry_paste_invalid'),
                    'map_failed' => __('parcels.geometry_map_failed'),
                ],
            ],
        ]);
    }

    /**
     * One transaction: lock the row, refuse if someone saved it since the
     * editor opened, write the boundary, audit it.
     *
     * @param  list<string>  $bindings
     */
    private function write(string $level, int $id, string $action, string $set, array $bindings): bool
    {
        $audit = self::LEVELS[$level]['audit'];

        try {
            $this->writeSafely($audit.'.'.$action, $audit, $id, function () use ($level, $id, $set, $bindings): void {
                $row = DB::table($level)->where('id', $id)->lockForUpdate()->first(['updated_at']);
                abort_if($row === null, 404);

                if ($this->openedAt !== null && (string) $row->updated_at !== $this->openedAt) {
                    throw new RuntimeException(__('common.conflict'));
                }

                DB::update(
                    "UPDATE {$level} SET {$set}, updated_at = ? WHERE id = ?",
                    [...$bindings, now(), $id]
                );
            });
        } catch (RuntimeException $exception) {
            // Only the optimistic-lock refusal is meant for the user; a
            // QueryException is a RuntimeException too and must not be shown.
            if ($exception->getMessage() !== __('common.conflict')) {
                throw $exception;
            }

            $this->addError('geometry', $exception->getMessage());

            return false;
        }

        return true;
    }

    private function finish(string $message): void
    {
        $this->show = false;
        $this->dispatch('boundary-saved', level: $this->level, id: $this->recordId);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /** @return array{0: string, 1: int} */
    private function editing(): array
    {
        abort_if($this->level === null || $this->recordId === null, 404);
        $this->authorize($this->level);

        return [$this->level, $this->recordId];
    }

    private function authorize(string $level): void
    {
        abort_unless(array_key_exists($level, self::LEVELS), 404);
        abort_unless(Auth::user()?->can('reference.edit'), 403);
        abort_unless(Dialect::isSpatial(), 404);
    }

    private function current(string $level, int $id): ?string
    {
        $row = DB::selectOne("SELECT ST_AsGeoJSON(geom, 7) AS g FROM {$level} WHERE id = ? AND geom IS NOT NULL", [$id]);

        return $row?->g === null ? null : (string) $row->g;
    }

    /** The other boundaries under the same parent, as a FeatureCollection. */
    private function neighbours(string $level, int $id): string
    {
        $config = self::LEVELS[$level];
        $parent = $config['parent'];

        $rows = $parent === null ? [] : DB::select(
            "SELECT n.name_ar AS name, ST_AsGeoJSON(ST_Simplify(n.geom, ?), 6) AS g
             FROM {$level} n JOIN {$level} self ON self.id = ?
             WHERE n.id <> self.id AND n.{$parent} = self.{$parent} AND n.geom IS NOT NULL
             LIMIT ".self::MAX_NEIGHBOURS,
            [$config['simplify'], $id]
        );

        return (string) json_encode([
            'type' => 'FeatureCollection',
            'features' => array_values(array_filter(array_map(static fn (object $row): ?array => $row->g === null ? null : [
                'type' => 'Feature',
                'geometry' => json_decode((string) $row->g, false),
                'properties' => ['name' => $row->name],
            ], $rows))),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Where to point the map when there is nothing drawn yet: the parent's
     * boundary (the city for a district, the region for a city), else the
     * kingdom.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function fallbackBounds(string $level, int $id): array
    {
        $parents = match ($level) {
            'districts' => [
                ['cities', 'SELECT city_id FROM districts WHERE id = ?'],
                ['regions', 'SELECT c.region_id FROM districts d JOIN cities c ON c.id = d.city_id WHERE d.id = ?'],
            ],
            'cities' => [['regions', 'SELECT region_id FROM cities WHERE id = ?']],
            default => [],
        };

        foreach ($parents as [$table, $parentId]) {
            $row = DB::selectOne(
                'SELECT '.Spatial::extentSelect('geom')." FROM {$table} WHERE id = ({$parentId}) AND geom IS NOT NULL",
                [$id]
            );

            if ($row !== null && $row->x1 !== null) {
                return [(float) $row->x1, (float) $row->y1, (float) $row->x2, (float) $row->y2];
            }
        }

        return [34.5, 16.3, 55.7, 32.2];
    }
}
