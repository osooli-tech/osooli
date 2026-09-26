<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ImportBatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes  Overrides merged over the defaults below —
     *                                            e.g. ['stored_path' => null] for a batch
     *                                            that never got as far as having a staged file.
     */
    private function batch(ImportStatus $status = ImportStatus::Uploading, array $attributes = []): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'batch'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        return ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Gdb,
            'status' => $status,
            'original_filename' => 'GDB.zip',
            'byte_size' => 1024,
            'stored_path' => 'imports/'.Str::uuid().'.zip',
            ...$attributes,
        ]);
    }

    public function test_it_casts_kind_and_status_to_enums(): void
    {
        $batch = $this->batch();

        $this->assertSame(ImportKind::Gdb, $batch->fresh()->kind);
        $this->assertSame(ImportStatus::Uploading, $batch->fresh()->status);
    }

    public function test_it_allows_a_legal_transition(): void
    {
        $batch = $this->batch();

        $this->assertTrue($batch->transitionTo(ImportStatus::Uploaded));
        $this->assertSame(ImportStatus::Uploaded, $batch->fresh()->status);
    }

    public function test_it_refuses_to_commit_without_a_preview(): void
    {
        $batch = $this->batch(ImportStatus::Uploaded);

        $this->assertFalse($batch->transitionTo(ImportStatus::Committing));
        $this->assertSame(ImportStatus::Uploaded, $batch->fresh()->status);
    }

    public function test_it_refuses_a_second_commit(): void
    {
        $batch = $this->batch(ImportStatus::Previewed);

        $this->assertTrue($batch->transitionTo(ImportStatus::Committing));
        $this->assertFalse($batch->fresh()->transitionTo(ImportStatus::Committing));
    }

    public function test_it_refuses_a_concurrent_second_commit_even_from_a_stale_in_memory_copy(): void
    {
        // Two independent copies of the same row, both loaded while it is still
        // Previewed — the shape a queued job and an HTTP request racing each
        // other actually takes, as opposed to test_it_refuses_a_second_commit()
        // above, which re-fetches before the second call and so never exercises
        // anything beyond the in-memory canTransitionTo() check.
        $batch = $this->batch(ImportStatus::Previewed);
        $staleCopy = ImportBatch::query()->find($batch->id);

        $this->assertTrue($batch->transitionTo(ImportStatus::Committing));

        // $staleCopy->status is still Previewed in memory, so its own
        // canTransitionTo() check would pass — only the database-level
        // compare-and-swap in transitionTo() can catch this.
        $this->assertFalse($staleCopy->transitionTo(ImportStatus::Committing));
        $this->assertSame(ImportStatus::Committing, $staleCopy->fresh()->status);
    }

    public function test_transitioning_with_an_array_attribute_keeps_it_an_array_in_memory(): void
    {
        // Regression test for a double-encoding bug: transitionTo() builds a
        // DB-ready $payload (where an 'array'-cast attribute is already a JSON
        // string) before it knows whether the compare-and-swap will succeed.
        // A version that reused that already-encoded $payload to update $this
        // in memory would run the 'array' cast a second time on the JSON
        // string itself, leaving $batch->preview a double-encoded string
        // instead of an array. This asserts the in-memory value directly,
        // without going through fresh(), since fresh() would mask the bug by
        // re-reading the (correctly single-encoded) row from the database.
        $batch = $this->batch(ImportStatus::Analyzing);

        $this->assertTrue($batch->transitionTo(ImportStatus::Previewed, [
            'preview' => ['total_items' => 8, 'warnings' => ['x']],
        ]));

        $this->assertIsArray($batch->preview);
        $this->assertSame(8, $batch->preview['total_items']);
        $this->assertSame(['x'], $batch->preview['warnings']);
    }

    public function test_marking_failed_records_the_message(): void
    {
        $batch = $this->batch(ImportStatus::Analyzing);

        $batch->markFailed('ogr2ogr was not found');

        $this->assertSame(ImportStatus::Failed, $batch->fresh()->status);
        $this->assertSame('ogr2ogr was not found', $batch->fresh()->error_message);
    }

    public function test_marking_failed_does_not_resurrect_a_completed_batch(): void
    {
        $batch = $this->batch(ImportStatus::Completed);

        $batch->markFailed('too late, already committed');

        $this->assertSame(ImportStatus::Completed, $batch->fresh()->status);
        $this->assertNull($batch->fresh()->error_message);
    }

    public function test_the_stale_scope_finds_old_batches_with_a_stored_file(): void
    {
        $old = $this->batch();
        $old->forceFill(['created_at' => now()->subDays(30)])->save();
        $this->batch();

        $this->assertSame(1, ImportBatch::query()->stale(7)->count());
    }

    public function test_the_stale_scope_ignores_old_batches_with_no_stored_file(): void
    {
        $old = $this->batch(attributes: ['stored_path' => null]);
        $old->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->assertSame(0, ImportBatch::query()->stale(7)->count());
    }
}
