<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Console\Commands\PruneImportBatches;
use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

final class PruneImportBatchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real filesystem calls (realpath(), is_dir()) against an isolated
        // fake root, exactly like DocumentImporterTest fakes 'public' — the
        // command under test resolves Storage::disk('local') the same way,
        // so it transparently picks up this fake too.
        Storage::fake('local');
    }

    /**
     * Stages a batch the way ImportUploadController actually does: the
     * archive lives at imports/{uuid}/source.zip, and stored_path is the
     * absolute path to it. $withFile = false simulates a row whose directory
     * was already removed by the controller's own failure-path cleanup
     * (deleteUpload()) while the row itself survives with a stale
     * stored_path.
     *
     * updated_at is backdated to the same age as created_at by default, so a
     * batch this helper stages behaves like a real untouched stale upload —
     * nothing has appended a chunk (which would touch updated_at, see
     * ImportUploadController::chunk()) since it was created. Pass
     * $updatedDaysAgo explicitly to simulate a batch that is old by
     * created_at but was touched more recently than that, i.e. one still
     * actively receiving chunks.
     */
    private function batch(int $ageInDays, ImportStatus $status = ImportStatus::Completed, bool $withFile = true, ?int $updatedDaysAgo = null): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'prune'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $uuid = (string) Str::uuid();
        $relative = 'imports/'.$uuid.'/source.zip';

        if ($withFile) {
            Storage::disk('local')->put($relative, 'staged archive');
        }

        $batch = ImportBatch::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => $status,
            'original_filename' => 'docs.zip',
            'byte_size' => 14,
            'stored_path' => Storage::disk('local')->path($relative),
        ]);

        // Both timestamps must be forced explicitly: Eloquent's save() only
        // skips overwriting updated_at with "now" when it is already dirty,
        // so forcing created_at alone would leave updated_at at its
        // creation-time value (effectively "now"), silently defeating the
        // command's updated_at filter for every test using this helper.
        $batch->forceFill([
            'created_at' => now()->subDays($ageInDays),
            'updated_at' => now()->subDays($updatedDaysAgo ?? $ageInDays),
        ])->save();

        return $batch;
    }

    public function test_it_deletes_staged_files_past_the_retention_window(): void
    {
        $batch = $this->batch(30);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        Storage::disk('local')->assertMissing('imports/'.$batch->uuid.'/source.zip');
    }

    public function test_it_keeps_the_batch_row_as_history(): void
    {
        $batch = $this->batch(30);

        $this->artisan('app:prune-import-batches');

        $this->assertDatabaseHas('import_batches', ['id' => $batch->id]);
        $this->assertNull($batch->fresh()->stored_path);
    }

    public function test_it_leaves_recent_batches_alone(): void
    {
        $batch = $this->batch(1);

        $this->artisan('app:prune-import-batches');

        Storage::disk('local')->assertExists('imports/'.$batch->uuid.'/source.zip');
        $this->assertNotNull($batch->fresh()->stored_path);
    }

    public function test_it_prunes_a_batch_still_uploading_when_the_upload_was_abandoned(): void
    {
        // stored_path is set at batch creation (Task 8), specifically so an
        // abandoned upload — tab closed mid-transfer, never reaching
        // Completed or Failed — is visible to this command instead of
        // leaking disk space forever. stale() makes no distinction on status,
        // and neither does this command.
        $batch = $this->batch(30, ImportStatus::Uploading);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        Storage::disk('local')->assertMissing('imports/'.$batch->uuid.'/source.zip');
        $this->assertNull($batch->fresh()->stored_path);
    }

    public function test_it_does_not_error_when_the_staging_directory_is_already_gone(): void
    {
        // Mirrors a batch ImportUploadController already cleaned up on one of
        // its own failure paths (size mismatch, unreadable archive): the row
        // survives with a stale stored_path but no directory behind it.
        $batch = $this->batch(30, ImportStatus::Failed, withFile: false);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        $this->assertDatabaseHas('import_batches', ['id' => $batch->id]);
        $this->assertNull($batch->fresh()->stored_path);
    }

    public function test_it_does_not_error_on_an_empty_stored_path(): void
    {
        // whereNotNull('stored_path') lets an empty string through (it is
        // not NULL), so the command must cope with one gracefully. It never
        // reads stored_path to decide what to delete anyway — only the
        // batch's own uuid — so an empty value here is simply irrelevant.
        $user = User::create([
            'name' => 'مدير', 'email' => 'prune'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => ImportStatus::Failed,
            'original_filename' => 'docs.zip',
            'byte_size' => 14,
            'stored_path' => '',
        ]);
        // Both timestamps forced — see the batch() helper's docblock for why
        // forcing created_at alone would leave updated_at effectively "now"
        // and wrongly exempt this row from the command's updated_at filter.
        $batch->forceFill([
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ])->save();

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        $this->assertNull($batch->fresh()->stored_path);
    }

    public function test_it_honours_the_days_override_option(): void
    {
        $batch = $this->batch(5);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);
        Storage::disk('local')->assertExists('imports/'.$batch->uuid.'/source.zip');

        $this->artisan('app:prune-import-batches', ['--days' => 3])->assertExitCode(0);
        Storage::disk('local')->assertMissing('imports/'.$batch->uuid.'/source.zip');
    }

    public function test_it_leaves_a_batch_alone_if_it_was_touched_within_the_retention_window(): void
    {
        // created_at is old enough to be stale by scopeStale() alone, but
        // chunk() touches updated_at on every appended chunk — so a batch
        // touched this recently is still an active upload, not an abandoned
        // one, however old it was first created. This is exactly the case
        // that would otherwise have its staging directory deleted out from
        // under an open, still-writing file handle.
        $batch = $this->batch(30, ImportStatus::Uploading, updatedDaysAgo: 1);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        Storage::disk('local')->assertExists('imports/'.$batch->uuid.'/source.zip');
        $this->assertNotNull($batch->fresh()->stored_path);
    }

    /**
     * All the tests above stage batches through batch(), which always goes
     * through Str::uuid() — a real, well-formed UUID that also happens to be
     * the only kind import_batches.uuid (a native Postgres uuid column) will
     * ever accept at insert time. That means none of them can ever exercise
     * the containment guard in deleteStagingDirectory(): a malformed uuid
     * value never survives to reach it through a normal batch. The guard is
     * still the single mechanism standing between this unattended, recursive
     * delete and the rest of the filesystem, so it is driven directly here,
     * via Reflection, with the adversarial values a corrupted or
     * future-buggy uuid column could one day hold.
     */
    public function test_the_containment_guard_refuses_to_touch_anything_outside_the_imports_root(): void
    {
        $disk = Storage::disk('local');

        // A sibling of the imports root whose name starts with the same
        // string ("imports") — exactly what a containment check without the
        // trailing DIRECTORY_SEPARATOR (str_starts_with($real, $importsRoot)
        // alone) would wrongly treat as "inside" imports.
        $disk->put('imports-evil/marker.txt', 'do not touch');

        // The imports root itself, the way a real batch would have created
        // it.
        $disk->put('imports/keepme/source.zip', 'staged archive');

        $importsRoot = realpath($disk->path('imports'));
        $this->assertIsString($importsRoot);

        $method = new ReflectionMethod(PruneImportBatches::class, 'deleteStagingDirectory');
        $method->setAccessible(true);
        $command = new PruneImportBatches;

        $adversarialUuids = [
            // Resolves, via realpath(), straight to the imports-evil sibling
            // above — the precise scenario the trailing-separator check
            // exists to catch.
            '../imports-evil',
            // Attempts to climb out past the imports root entirely.
            '../../../../etc',
            // A value that looks like an absolute path rather than a bare
            // uuid segment.
            DIRECTORY_SEPARATOR.'etc'.DIRECTORY_SEPARATOR.'passwd',
        ];

        foreach ($adversarialUuids as $uuid) {
            $result = $method->invoke($command, $disk, $importsRoot, $uuid);

            $this->assertNotTrue($result, "uuid '{$uuid}' must never be reported as deleted");
        }

        // The assertion that actually proves the guard: the sibling survives
        // every adversarial call above, and the legitimate imports
        // subdirectory was never touched either.
        $disk->assertExists('imports-evil/marker.txt');
        $disk->assertExists('imports/keepme/source.zip');
    }
}
