<?php

declare(strict_types=1);

namespace App\Livewire\Exports;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\DatabaseEnum;
use App\Support\Export\DeedExportFilters;
use App\Support\Export\DeedGeoJsonExporter;
use App\Support\Export\ExportRuns;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * The bulk export page: filter the deeds, choose what each one carries, and
 * export them as GeoJSON — thousands at a time, in the background, with the
 * file kept for download for ExportRuns::KEEP_DAYS days.
 */
class ExportCenter extends Component
{
    /** @var array<string, mixed> */
    public array $filters = DeedExportFilters::DEFAULTS;

    /** @var list<string> */
    public array $groups = DeedGeoJsonExporter::GROUPS;

    /** Id of the run this page started and is following, if any. */
    public ?string $following = null;

    public function mount(): void
    {
        $this->authorize('exports.bulk');

        ExportRuns::prune();
    }

    public function updatedFilters(mixed $value, string $key): void
    {
        // A narrower place invalidates a narrower one picked under the old one.
        if ($key === 'region_id') {
            $this->filters['city_id'] = '';
            $this->filters['district_id'] = '';
        } elseif ($key === 'city_id') {
            $this->filters['district_id'] = '';
        }
    }

    public function resetFilters(): void
    {
        $this->filters = DeedExportFilters::DEFAULTS;
    }

    public function export(): void
    {
        $this->authorize('exports.bulk');

        $this->validate([
            'groups' => ['array'],
            'groups.*' => ['in:'.implode(',', DeedGeoJsonExporter::GROUPS)],
            'filters.area_min' => ['nullable', 'numeric', 'min:0'],
            'filters.area_max' => ['nullable', 'numeric', 'min:0'],
            'filters.price_min' => ['nullable', 'numeric', 'min:0'],
            'filters.price_max' => ['nullable', 'numeric', 'min:0'],
            'filters.date_from' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'filters.date_to' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ], [], [
            'filters.area_min' => __('exports_center.area'),
            'filters.area_max' => __('exports_center.area'),
            'filters.price_min' => __('exports_center.price'),
            'filters.price_max' => __('exports_center.price'),
            'filters.date_from' => __('exports_center.deed_date'),
            'filters.date_to' => __('exports_center.deed_date'),
        ]);

        /** @var User $user */
        $user = Auth::user();
        $filters = new DeedExportFilters($this->filters);

        if ($filters->query($user)->count() === 0) {
            $this->dispatch('toast', type: 'warning', message: __('exports_center.nothing_to_export'));

            return;
        }

        $id = ExportRuns::newId();
        $groups = $this->groups;

        // Shown at once; the export overwrites it as soon as it starts.
        ExportRuns::save([
            'id' => $id, 'state' => 'running', 'user_id' => $user->id, 'user_name' => $user->name,
            'started_at' => now()->toIso8601String(), 'finished_at' => null,
            'filters' => $filters->active(), 'groups' => $groups, 'total' => 0, 'done' => 0, 'bytes' => 0, 'error' => null,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'export_bulk',
            'target_type' => 'export',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);

        // After the response is sent, so a large export does not hold the
        // page open; the page polls the run for progress.
        \Illuminate\Support\defer(static function () use ($id, $filters, $groups, $user): void {
            try {
                app(DeedGeoJsonExporter::class)->run($id, $filters, $groups, $user);
            } catch (Throwable $e) {
                report($e);
            }
        });

        $this->following = $id;
        $this->dispatch('toast', type: 'success', message: __('exports_center.started'));
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $count = null;
        try {
            $count = (new DeedExportFilters($this->filters))->query($user)->count();
        } catch (Throwable) {
            // A half-typed number is not worth an error page; the count waits.
        }

        $current = $this->following === null ? null : ExportRuns::find($this->following);

        return view('livewire.exports.export-center', [
            'count' => $count,
            'current' => $current,
            'running' => ($current['state'] ?? null) === 'running',
            // Administrators see everyone's exports; others only their own.
            'history' => ExportRuns::recent($user->can('roles.manage') ? null : $user->id),
            'enums' => [
                'deed_status' => DatabaseEnum::for('deed_status'),
                'deed_class' => DatabaseEnum::for('deed_class'),
                'asset_type' => DatabaseEnum::for('asset_type'),
                'fall_in' => DatabaseEnum::for('fall_in'),
                'land_transaction' => DatabaseEnum::for('land_transaction'),
                'allocation_method' => DatabaseEnum::for('allocation_method'),
            ],
        ]);
    }
}
