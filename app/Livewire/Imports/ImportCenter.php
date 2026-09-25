<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Import\DeedImportAnalyzer;
use App\Support\Import\DeedImportApplier;
use App\Support\Import\DeedImportUndo;
use App\Support\Import\ImportRuns;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;
use ZipArchive;

/**
 * The import page: upload a GeoJSON file in the export's format, see what it
 * would do, settle what needs a person's judgement, confirm, apply — and
 * undo it within ImportRuns::KEEP_DAYS days if it turns out wrong.
 */
class ImportCenter extends Component
{
    use WithFileUploads;

    private const PER_PAGE = 25;

    /** @var TemporaryUploadedFile|null */
    #[Validate('required|file|max:204800')]
    public $upload = null;

    public ?string $following = null;

    /** Which analysed items the list shows: all, new, changed, same, decision, error. */
    public string $show = 'all';

    public int $page = 1;

    /** Decisions made on this page, by md5 of the decision key. */
    public array $choices = [];

    /** @var list<int> indexes left out of the import */
    public array $excluded = [];

    public bool $confirmed = false;

    public ?int $open = null;

    public function mount(): void
    {
        $this->authorize('imports.run');
        ImportRuns::prune();
    }

    public function analyse(): void
    {
        $this->authorize('imports.run');
        $this->validate();

        $extension = strtolower($this->upload->getClientOriginalExtension());
        if (! in_array($extension, ['geojson', 'json', 'zip'], true)) {
            $this->addError('upload', __('imports_center.bad_extension'));

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $id = ImportRuns::newId();
        $target = ImportRuns::path($id, 'source.geojson');

        if ($extension === 'zip') {
            if (! $this->unzip($this->upload->getRealPath(), $target)) {
                $this->addError('upload', __('imports_center.bad_zip'));

                return;
            }
        } else {
            copy($this->upload->getRealPath(), $target);
        }

        ImportRuns::save([
            'id' => $id, 'state' => 'analysing', 'user_id' => $user->id, 'user_name' => $user->name,
            'file' => $this->upload->getClientOriginalName(), 'size' => filesize($target) ?: 0,
            'created_at' => now()->toIso8601String(), 'done' => 0,
        ]);

        $this->reset('upload', 'choices', 'excluded', 'confirmed', 'open', 'show', 'page');
        $this->following = $id;

        \Illuminate\Support\defer(static function () use ($id, $user): void {
            app(DeedImportAnalyzer::class)->run($id, $user);
        });
    }

    public function follow(string $id): void
    {
        abort_unless(ImportRuns::isValidId($id), 404);
        $this->reset('choices', 'excluded', 'confirmed', 'open', 'show', 'page');
        $this->following = $id;

        $run = $this->run();
        foreach ((array) ($run['choices'] ?? []) as $key => $value) {
            $this->choices[md5($key)] = (string) $value;
        }
        $this->excluded = array_map('intval', (array) ($run['excluded'] ?? []));
    }

    public function showOnly(string $status): void
    {
        $this->show = $status;
        $this->page = 1;
        $this->open = null;
    }

    public function toggleExcluded(int $index): void
    {
        $this->excluded = in_array($index, $this->excluded, true)
            ? array_values(array_diff($this->excluded, [$index]))
            : [...$this->excluded, $index];
    }

    public function apply(): void
    {
        $this->authorize('imports.run');
        $run = $this->run();

        if (($run['state'] ?? null) !== 'analysed') {
            return;
        }

        $choices = [];
        foreach (array_keys((array) ($run['decisions_needed'] ?? [])) as $key) {
            $value = $this->choices[md5($key)] ?? '';
            if ($value === '') {
                $this->dispatch('toast', type: 'error', message: __('imports_center.decisions_pending'));

                return;
            }
            $choices[$key] = ctype_digit($value) ? (int) $value : $value;
        }

        if ((($run['counts']['changed'] ?? 0) > 0 || ($run['counts']['new'] ?? 0) > 0) && ! $this->confirmed) {
            $this->addError('confirmed', __('imports_center.confirm_required'));

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $run['choices'] = $choices;
        $run['excluded'] = $this->excluded;
        $run['state'] = 'applying';
        ImportRuns::save($run);

        AuditLog::create([
            'user_id' => $user->id, 'action' => 'import_apply', 'target_type' => 'import',
            'ip_address' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);

        $id = (string) $this->following;
        \Illuminate\Support\defer(static function () use ($id, $user): void {
            app(DeedImportApplier::class)->run($id, $user);
        });
    }

    public function undo(): void
    {
        $this->authorize('imports.run');
        $run = $this->run();

        if (($run['state'] ?? null) !== 'applied') {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $run['state'] = 'undoing';
        ImportRuns::save($run);

        AuditLog::create([
            'user_id' => $user->id, 'action' => 'import_undo', 'target_type' => 'import',
            'ip_address' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);

        $id = (string) $this->following;
        \Illuminate\Support\defer(static function () use ($id, $user): void {
            app(DeedImportUndo::class)->run($id, $user);
        });
    }

    public function close(): void
    {
        $this->reset('following', 'choices', 'excluded', 'confirmed', 'open', 'show', 'page');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $run = $this->run();
        $items = [];
        $matching = 0;

        if (in_array($run['state'] ?? null, ['analysed', 'applying', 'applied', 'apply_failed', 'undoing', 'undone'], true)) {
            $from = ($this->page - 1) * self::PER_PAGE;
            foreach (ImportRuns::items((string) $this->following) as $item) {
                if ($this->show !== 'all' && $item['status'] !== $this->show) {
                    continue;
                }
                if ($matching >= $from && $matching < $from + self::PER_PAGE) {
                    $items[] = $item;
                }
                $matching++;
            }
        }

        return view('livewire.imports.import-center', [
            'run' => $run,
            'busy' => in_array($run['state'] ?? null, ['analysing', 'applying', 'undoing'], true),
            'items' => $items,
            'pages' => max(1, (int) ceil($matching / self::PER_PAGE)),
            'history' => ImportRuns::recent($user->can('roles.manage') ? null : $user->id),
        ]);
    }

    /** @return array<string, mixed> */
    private function run(): array
    {
        if ($this->following === null) {
            return [];
        }

        $run = ImportRuns::find($this->following) ?? [];

        // Only its owner or an administrator may see an import.
        /** @var User $user */
        $user = Auth::user();
        if ($run !== [] && (int) ($run['user_id'] ?? 0) !== $user->id && ! $user->can('roles.manage')) {
            abort(403);
        }

        return $run;
    }

    /** Extract the first .geojson / .json file in the archive to `$target`. */
    private function unzip(string $zipPath, string $target): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('/\.(geo)?json$/i', $name) && ! str_contains($name, '__MACOSX')) {
                    $in = $zip->getStream($name);
                    $out = fopen($target, 'wb');
                    if ($in === false || $out === false) {
                        return false;
                    }
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);

                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        } finally {
            $zip->close();
        }

        return false;
    }
}
