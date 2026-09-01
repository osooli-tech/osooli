<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

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

        $this->actingAs($this->admin())->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'x'),
        ])->assertForbidden();
    }
}
