<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportStatus;
use App\Jobs\AnalyzeImportBatch;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

final class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'upload'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');
        $user->givePermissionTo('imports.create');

        return $user;
    }

    private function engineer(): User
    {
        $user = User::create([
            'name' => 'مساح', 'email' => 'eng'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');

        return $user;
    }

    private function start(User $user, int $size = 12, string $name = 'docs.zip'): string
    {
        return $this->actingAs($user)
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => $name, 'byte_size' => $size,
            ])
            ->json('uuid');
    }

    /** Builds a genuinely valid, tiny ZIP archive on disk and returns its raw bytes. */
    private function tinyZipBytes(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import-test-zip');
        $this->assertIsString($tmp);

        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('a.txt', 'hello');
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }

    public function test_a_user_without_the_permission_cannot_start_an_upload(): void
    {
        $this->actingAs($this->engineer())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 10,
            ])
            ->assertForbidden();
    }

    public function test_it_creates_a_batch_in_the_uploading_state(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->postJson(route('imports.upload.create'), [
            'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 10,
        ]);

        $response->assertOk()->assertJsonStructure(['uuid', 'chunk_bytes']);
        $this->assertDatabaseHas('import_batches', [
            'uuid' => $response->json('uuid'),
            'status' => ImportStatus::Uploading->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_it_rejects_a_rar_upload(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.rar', 'byte_size' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_it_rejects_a_file_over_the_size_cap(): void
    {
        config(['imports.max_upload_bytes' => 100]);

        $this->actingAs($this->admin())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 101,
            ])
            ->assertStatus(422);
    }

    public function test_it_accepts_chunks_in_order(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 12);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'hello '),
        ])->assertOk()->assertJson(['next_index' => 1]);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 1, 'chunk' => UploadedFile::fake()->createWithContent('c1', 'world!'),
        ])->assertOk()->assertJson(['next_index' => 2]);
    }

    public function test_an_out_of_order_chunk_is_rejected_with_the_expected_index(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 5, 'chunk' => UploadedFile::fake()->createWithContent('c5', 'oops'),
        ])->assertStatus(409)->assertJson(['expected_index' => 0]);
    }

    public function test_completing_with_the_wrong_size_fails_the_batch(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 999);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'short'),
        ])->assertOk();

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(422);

        $this->assertSame(ImportStatus::Failed, ImportBatch::where('uuid', $uuid)->first()->status);
    }

    public function test_a_user_cannot_touch_another_users_batch(): void
    {
        $uuid = $this->start($this->admin());

        // Collapsed to 404, not 403: a missing batch and one owned by
        // someone else must be indistinguishable to the caller, matching how
        // this app's mobile API already treats existence vs. ownership
        // elsewhere (bootstrap/app.php).
        $this->actingAs($this->admin())->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'x'),
        ])->assertNotFound();
    }

    public function test_a_malformed_uuid_is_rejected_before_reaching_the_database(): void
    {
        // {uuid} is route-constrained to the uuid shape, so a value that
        // cannot possibly match a row never reaches the "uuid" column's
        // native Postgres type as a comparison value (which would otherwise
        // raise a SQL error visible to the client under APP_DEBUG).
        $this->actingAs($this->admin())
            ->postJson('/imports/upload/not-a-uuid/chunk', [
                'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'x'),
            ])
            ->assertNotFound();
    }

    public function test_a_geojson_upload_is_stored_with_its_real_extension(): void
    {
        $user = $this->admin();

        $uuid = $this->actingAs($user)->postJson(route('imports.upload.create'), [
            'kind' => 'gdb', 'filename' => 'parcels.geojson', 'byte_size' => 10,
        ])->json('uuid');

        // GdbConverter's .geojson/.json passthrough decides whether to run
        // GDAL at all by testing stored_path's own suffix — losing the real
        // extension here makes an explicitly whitelisted format unreachable.
        $storedPath = ImportBatch::where('uuid', $uuid)->value('stored_path');
        $this->assertNotNull($storedPath);
        $this->assertStringEndsWith('.geojson', (string) $storedPath);
    }

    public function test_a_freshly_created_batch_already_has_a_stored_path(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user);

        // Set at create() time, before a single chunk arrives, so an
        // abandoned upload is visible to ImportBatch::scopeStale() (it
        // filters on whereNotNull('stored_path')) instead of hiding from the
        // retention job precisely because it never completed.
        $this->assertNotNull(ImportBatch::where('uuid', $uuid)->value('stored_path'));
    }

    public function test_a_size_mismatch_deletes_the_uploaded_file(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 999);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'short'),
        ])->assertOk();

        $storedPath = (string) ImportBatch::where('uuid', $uuid)->value('stored_path');
        $this->assertFileExists($storedPath);

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(422);

        $this->assertFileDoesNotExist($storedPath);
    }

    public function test_chunks_that_together_exceed_the_declared_size_are_rejected(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 10);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', '123456'),
        ])->assertOk();

        // 6 + 6 = 12 bytes total against a declared byte_size of 10 — every
        // individual chunk and every index is well-formed, only the running
        // total is over budget.
        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 1, 'chunk' => UploadedFile::fake()->createWithContent('c1', '123456'),
        ])->assertStatus(422);

        $this->assertSame(ImportStatus::Failed, ImportBatch::where('uuid', $uuid)->first()->status);
    }

    public function test_a_chunk_is_rejected_once_the_batch_is_no_longer_uploading(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user);

        $batch = ImportBatch::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($batch->transitionTo(ImportStatus::Failed, ['error_message' => 'already failed']));

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'x'),
        ])->assertStatus(409);
    }

    public function test_complete_is_rejected_once_the_batch_is_no_longer_uploading(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user);

        $batch = ImportBatch::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($batch->transitionTo(ImportStatus::Failed, ['error_message' => 'already failed']));

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(409);
    }

    public function test_completing_an_unopenable_zip_fails_the_batch(): void
    {
        $user = $this->admin();
        $garbage = 'this is definitely not a zip archive';
        $uuid = $this->start($user, strlen($garbage), 'docs.zip');

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', $garbage),
        ])->assertOk();

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(422);

        $this->assertSame(ImportStatus::Failed, ImportBatch::where('uuid', $uuid)->first()->status);
    }

    public function test_completing_invalid_geojson_content_fails_the_batch(): void
    {
        $user = $this->admin();
        $content = '{"type":"NotAFeatureCollection"}';

        $uuid = $this->actingAs($user)->postJson(route('imports.upload.create'), [
            'kind' => 'gdb', 'filename' => 'parcels.geojson', 'byte_size' => strlen($content),
        ])->json('uuid');

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', $content),
        ])->assertOk();

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(422);

        $this->assertSame(ImportStatus::Failed, ImportBatch::where('uuid', $uuid)->first()->status);
    }

    public function test_completing_twice_dispatches_analysis_only_once(): void
    {
        Bus::fake();

        $bytes = $this->tinyZipBytes();
        $user = $this->admin();
        $uuid = $this->start($user, strlen($bytes), 'docs.zip');

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', $bytes),
        ])->assertOk();

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertOk();

        // The second call finds the batch already Uploaded (not Uploading)
        // and must not dispatch a second analysis job for it.
        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(409);

        Bus::assertDispatched(AnalyzeImportBatch::class, 1);
    }
}
