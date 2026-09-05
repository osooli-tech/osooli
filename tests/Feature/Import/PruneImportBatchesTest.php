<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     */
    private function batch(int $ageInDays, ImportStatus $status = ImportStatus::Completed, bool $withFile = true): ImportBatch
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

        $batch->forceFill(['created_at' => now()->subDays($ageInDays)])->save();

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
        $batch->forceFill(['created_at' => now()->subDays(30)])->save();

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
}
