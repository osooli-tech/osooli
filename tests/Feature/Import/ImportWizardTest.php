<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Livewire\Imports\ImportWizard;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ImportWizardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'wiz'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');
        $user->givePermissionTo('imports.create');

        return $user;
    }

    private function batch(User $user, ImportStatus $status): ImportBatch
    {
        return ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => $status,
            'original_filename' => 'docs.zip',
            'byte_size' => 10,
            'stored_path' => '/tmp/nonexistent.zip',
            'preview' => ['total_items' => 27, 'will_create' => 27, 'will_update' => 0, 'unmatched' => 0, 'details' => [], 'warnings' => []],
        ]);
    }

    public function test_the_page_requires_the_permission(): void
    {
        $this->withoutVite();

        $user = User::create([
            'name' => 'مساح', 'email' => 'nope'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/imports')->assertForbidden();
    }

    public function test_an_authorised_admin_can_open_the_page(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin())->get('/imports')->assertOk();
    }

    public function test_it_shows_the_preview_counts(): void
    {
        $user = $this->admin();
        $batch = $this->batch($user, ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->assertSee('27');
    }

    public function test_confirming_dispatches_the_commit(): void
    {
        config(['imports.queue_sync' => true]);
        $user = $this->admin();
        $batch = $this->batch($user, ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm');

        // The staged file does not exist, so the commit fails — which still
        // proves the job ran rather than being silently skipped.
        $this->assertContains($batch->fresh()->status, [ImportStatus::Completed, ImportStatus::Failed]);
        $this->assertNotSame(ImportStatus::Previewed, $batch->fresh()->status);
    }

    public function test_mounting_with_another_users_batch_uuid_does_not_render_its_detail(): void
    {
        $owner = $this->admin();
        $batch = $this->batch($owner, ImportStatus::Previewed);

        // batchUuid is a public, client-settable Livewire property. Pointing
        // it at another user's batch must not render that batch's preview —
        // the component should fall back to its "no batch yet" upload state
        // instead of leaking preview counts/warnings for a batch this user
        // does not own.
        Livewire::actingAs($this->admin())
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->assertDontSee('27')
            ->assertSee(__('imports.choose_file'));
    }

    public function test_confirming_someone_elses_batch_is_forbidden(): void
    {
        $owner = $this->admin();
        $batch = $this->batch($owner, ImportStatus::Previewed);

        Livewire::actingAs($this->admin())
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm')
            ->assertForbidden();
    }

    public function test_a_user_without_the_permission_cannot_call_confirm(): void
    {
        $user = User::create([
            'name' => 'مساح', 'email' => 'nc'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $batch = $this->batch($this->admin(), ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm')
            ->assertForbidden();
    }
}
