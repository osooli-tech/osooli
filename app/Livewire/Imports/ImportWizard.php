<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\MapLayer;
use App\Services\Import\CustomLayerImporter;
use App\Services\Import\DisplayLayerImporter;
use App\Services\Import\GdbImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ImportWizard extends Component
{
    public ?string $batchUuid = null;

    /**
     * The review screen's decisions for a geodatabase: which layer is which,
     * where each district belongs, which boundary set counts, and so on.
     * Filled from the analysis's suggestions, edited on screen, saved with
     * the batch on confirm.
     *
     * @var array<string, mixed>
     */
    public array $options = [];

    /** The batch $options were filled for, so a poll never resets edits. */
    #[Locked]
    public ?string $optionsFor = null;

    public function mount(?string $batchUuid = null): void
    {
        $this->batchUuid = $batchUuid;
    }

    /**
     * The batch shown by render() — scoped to the current user.
     *
     * $batchUuid is a public Livewire property, so it is client-settable:
     * any authenticated user holding imports.create could otherwise point it
     * at someone else's batch and have it fully rendered here (preview
     * counts, warnings, result breakdown, error_message). Scoping the lookup
     * itself means a foreign uuid resolves to null and the component falls
     * back to its "no batch yet" state, exactly as if $batchUuid had never
     * been set — it never proves whether that uuid belongs to someone else.
     *
     * This intentionally does not use #[Locked]: the upload JS legitimately
     * sets this property client-side via $wire.set('batchUuid', uuid) once
     * create() returns, and #[Locked] would reject that.
     */
    public function batch(): ?ImportBatch
    {
        return $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->where('user_id', auth()->id())->first();
    }

    /**
     * Livewire actions are client-callable, so this authorizes server-side
     * rather than relying on the blade @can that hides the button.
     */
    public function confirm(): void
    {
        $this->authorize('imports.create');

        // Looked up unscoped (unlike batch() above) so that a batch which
        // exists but belongs to someone else is distinguished from one that
        // does not exist at all: 403 for the former, 404 for the latter.
        $batch = $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->first();

        abort_if($batch === null, 404);
        abort_unless($batch->user_id === auth()->id(), 403);

        if ($batch->status !== ImportStatus::Previewed) {
            return;
        }

        if ($batch->stored_path === null) {
            // PruneImportBatches reaps a staged batch's file after the
            // retention window regardless of status — including Previewed —
            // and nulls stored_path when it does. Without this check, a
            // stale confirm click would dispatch a commit that immediately
            // fails to find the file and reports CommitImportBatch's partial
            // -write notice ("Some records may already be committed") for a
            // write that never even started. Refusing here, with its own
            // terminal Failed status and a message that says what actually
            // happened, is markedly better than that false alarm — see I6 in
            // the final review.
            $batch->markFailed(__('imports.errors.staged_file_missing'));

            return;
        }

        if (is_array($batch->preview['details']['gdb'] ?? null)) {
            $batch->update(['options' => $this->cleanOptions($batch->preview['details']['gdb'])]);
        }

        $batch->dispatchCommit();
    }

    /**
     * The decisions as they may be applied: only layers the file has, only
     * the roles, modes and choices there are, ids as integers. Anything the
     * browser sent beyond that is dropped.
     *
     * @param  array<string, mixed>  $gdb  the analysis the choices were made on
     * @return array<string, mixed>
     */
    private function cleanOptions(array $gdb): array
    {
        $o = $this->options;
        $names = array_column($gdb['layers'] ?? [], 'name');
        $int = static fn (mixed $v): ?int => is_numeric($v) && (int) $v > 0 ? (int) $v : null;
        $pick = static fn (mixed $v, array $allowed, string $default): string => in_array($v, $allowed, true) ? (string) $v : $default;

        $customIds = MapLayer::query()->pluck('id')->all();
        $layers = [];
        foreach ((array) ($o['layers'] ?? []) as $choice) {
            if (is_array($choice) && in_array($choice['name'] ?? null, $names, true)) {
                $target = $int($choice['target'] ?? null);
                $layers[] = [
                    'name' => (string) $choice['name'],
                    'role' => $pick($choice['role'] ?? null, GdbImporter::ROLES, 'ignore'),
                    // An existing custom layer to add to or replace; null makes a new one.
                    'target' => in_array($target, $customIds, true) ? $target : null,
                    'new_name' => mb_substr(trim((string) ($choice['new_name'] ?? '')), 0, 140) ?: (string) $choice['name'],
                    'mode' => $pick($choice['mode'] ?? null, CustomLayerImporter::MODES, 'replace'),
                ];
            }
        }

        $districts = [];
        foreach ((array) ($o['districts'] ?? []) as $row) {
            if (is_array($row) && is_string($row['name'] ?? null)) {
                $districts[] = [
                    'name' => $row['name'],
                    'district_id' => $int($row['district_id'] ?? null),
                    'city_id' => $int($row['city_id'] ?? null),
                    'match' => $pick($row['match'] ?? null, ['name', 'map'], ''),
                    'new_name' => mb_substr(trim((string) ($row['new_name'] ?? '')), 0, 150),
                ];
            }
        }

        $geoIds = array_column($gdb['analysis']['parcel_list'] ?? [], 'geo_id');
        $parcelDistricts = [];
        foreach ((array) ($o['parcel_districts'] ?? []) as $exception) {
            $districtId = is_array($exception) ? $int($exception['district_id'] ?? null) : null;
            if ($districtId !== null && in_array($exception['geo_id'] ?? null, $geoIds, true)) {
                $parcelDistricts[] = ['geo_id' => (string) $exception['geo_id'], 'district_id' => $districtId];
            }
        }

        return [
            'layers' => $layers,
            'district_match' => $pick($o['district_match'] ?? null, ['name', 'map'], 'name'),
            'parcel_districts' => $parcelDistricts,
            'modes' => [
                'projects' => $pick($o['modes']['projects'] ?? null, DisplayLayerImporter::MODES, 'replace'),
                'buildings' => $pick($o['modes']['buildings'] ?? null, DisplayLayerImporter::MODES, 'replace'),
            ],
            'districts' => $districts,
            'default_city_id' => $int($o['default_city_id'] ?? null),
            'plan_placeholders' => mb_substr(trim((string) ($o['plan_placeholders'] ?? '')), 0, 500),
            'borders' => $pick($o['borders'] ?? null, ['first', 'second', 'prefer_second'], 'first'),
            'qrar' => $pick($o['qrar'] ?? null, ['number', 'source', 'ignore'], 'ignore'),
            'folder' => $pick($o['folder'] ?? null, ['folder', 'ignore'], 'ignore'),
            'portfolios' => (bool) ($o['portfolios'] ?? false),
            'deedless' => $pick($o['deedless'] ?? null, ['placeholder', 'skip'], 'placeholder'),
            'no_plan' => $pick($o['no_plan'] ?? null, ['district_plan', 'none'], 'district_plan'),
            'office_id' => $int($o['office_id'] ?? null),
            'office_name' => mb_substr(trim((string) ($o['office_name'] ?? '')), 0, 150),
            // Recorded on the custom layers this import creates.
            'source_name' => $this->batch()?->original_filename,
            'user_id' => auth()->id(),
        ];
    }

    /**
     * Point a layer of the file at the one its name resembles — a custom
     * layer on the map (to add to it) or a built-in role — from the warning
     * on the review screen.
     */
    public function useSimilar(int $index, string $kind, string $value): void
    {
        if (! isset($this->options['layers'][$index])) {
            return;
        }

        if ($kind === 'custom' && MapLayer::whereKey((int) $value)->exists()) {
            $this->options['layers'][$index]['role'] = 'custom';
            $this->options['layers'][$index]['target'] = (int) $value;
            $this->options['layers'][$index]['mode'] = 'append';
        } elseif ($kind === 'built_in' && in_array($value, ['parcels', 'projects', 'buildings'], true)) {
            $this->options['layers'][$index]['role'] = $value;
        }
    }

    /** One more parcel to give a district of its own, on the review screen. */
    public function addParcelException(): void
    {
        $this->options['parcel_districts'][] = ['geo_id' => '', 'district_id' => null];
    }

    public function removeParcelException(int $index): void
    {
        unset($this->options['parcel_districts'][$index]);
        $this->options['parcel_districts'] = array_values($this->options['parcel_districts'] ?? []);
    }

    /** Back to one of one's own imports — to review it again, or read its result. */
    public function open(string $uuid): void
    {
        $batch = ImportBatch::where('uuid', $uuid)->where('user_id', auth()->id())->first();
        abort_if($batch === null, 404);

        $this->batchUuid = $batch->uuid;
    }

    public function startOver(): void
    {
        $this->batchUuid = null;
        $this->options = [];
        $this->optionsFor = null;
    }

    public function render(): View
    {
        $batch = $this->batch();

        // The suggestions become the editable choices once, when the
        // analysis first arrives — never again on a later render or poll.
        $suggested = $batch?->preview['details']['gdb']['suggested'] ?? null;
        if ($batch !== null && is_array($suggested) && $this->optionsFor !== $batch->uuid) {
            $this->options = $suggested;
            $this->optionsFor = $batch->uuid;
        }

        return view('livewire.imports.import-wizard', [
            'offices' => is_array($suggested) ? DB::table('engineering_offices')->orderBy('name')->pluck('name', 'id')->all() : [],
            'customLayers' => is_array($suggested) ? MapLayer::query()->orderBy('name')->get(['id', 'name', 'feature_count']) : collect(),
            'currentBatch' => $batch,
            'recent' => ImportBatch::query()->with('user')->latest()->limit(10)->get(),
        ]);
    }
}
