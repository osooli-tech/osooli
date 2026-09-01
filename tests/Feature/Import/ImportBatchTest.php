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

    private function batch(ImportStatus $status = ImportStatus::Uploading): ImportBatch
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

    public function test_marking_failed_records_the_message(): void
    {
        $batch = $this->batch(ImportStatus::Analyzing);

        $batch->markFailed('ogr2ogr was not found');

        $this->assertSame(ImportStatus::Failed, $batch->fresh()->status);
        $this->assertSame('ogr2ogr was not found', $batch->fresh()->error_message);
    }

    public function test_the_stale_scope_finds_old_batches(): void
    {
        $old = $this->batch();
        $old->forceFill(['created_at' => now()->subDays(30)])->save();
        $this->batch();

        $this->assertSame(1, ImportBatch::query()->stale(7)->count());
    }
}
