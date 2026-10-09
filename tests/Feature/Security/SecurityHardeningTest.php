<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Livewire\Settings\RoleManager;
use App\Livewire\Users\UserIndex;
use App\Models\AuditLog;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\User;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The fixes from the October 2026 security review — each test is the attack
 * that used to work.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_code_is_burnt_after_five_wrong_guesses(): void
    {
        config(['auth.mobile_otp.test_code' => '6666']);
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $otp = app(OwnerOtpService::class);
        $otp->issue($owner);

        foreach (range(1, 5) as $guess) {
            $this->assertFalse($otp->verify($owner, '000'.$guess));
        }

        $this->assertFalse($otp->verify($owner, '6666'), 'the right code no longer works once the guesses ran out');
    }

    public function test_a_staff_code_is_burnt_after_five_wrong_guesses(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Cache::put("otp_{$user->id}", '123456', now()->addMinutes(5));

        foreach (range(1, 5) as $guess) {
            $this->withSession(['otp_user_id' => $user->id])->post(route('otp.verify'), ['otp' => '00000'.$guess]);
        }

        $this->withSession(['otp_user_id' => $user->id])->post(route('otp.verify'), ['otp' => '123456'])
            ->assertSessionHasErrors('otp');
        $this->assertGuest();
    }

    public function test_a_user_who_can_only_view_users_cannot_create_one(): void
    {
        Role::findOrCreate('engineer', 'web');
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(Permission::findOrCreate('users.view', 'web'));

        Livewire::actingAs($viewer)->test(UserIndex::class)
            ->set('formName', 'دخيل')->set('formEmail', 'intruder@example.com')
            ->set('formPassword', 'long-enough-password')->set('formRole', 'engineer')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_nobody_can_grant_a_role_holding_permissions_they_lack(): void
    {
        $admin = Role::findOrCreate('super_admin', 'web');
        $admin->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));

        $manager = User::factory()->create(['is_active' => true]);
        $manager->givePermissionTo([
            Permission::findOrCreate('users.view', 'web'),
            Permission::findOrCreate('users.edit', 'web'),
        ]);

        Livewire::actingAs($manager)->test(UserIndex::class)
            ->call('openEdit', $manager->id)
            ->set('formRole', 'super_admin')
            ->call('save')
            ->assertForbidden();

        $this->assertFalse($manager->fresh()->hasRole('super_admin'));
    }

    public function test_the_role_manager_refuses_anyone_without_roles_manage(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($user)->test(RoleManager::class)->assertForbidden();
    }

    public function test_a_former_owner_cannot_download_a_document_through_the_api(): void
    {
        Storage::fake(ParcelPhoto::PRIVATE_DISK);
        [$former, $current, $parcel] = $this->parcelThatChangedHands();
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('deeds/x.pdf', 'pdf');
        $document = ParcelPhoto::create([
            'parcel_id' => $parcel->id, 'photo_url' => 'deeds/x.pdf', 'storage_disk' => ParcelPhoto::PRIVATE_DISK,
            'photo_type' => PhotoType::Deed->value,
        ]);

        $this->withToken($former->createToken('t')->plainTextToken)
            ->get("/api/v1/documents/{$document->id}/download")->assertNotFound();

        // Within one test the guard would otherwise keep answering as the first caller.
        $this->app['auth']->forgetGuards();
        $this->withToken($current->createToken('t')->plainTextToken)
            ->get("/api/v1/documents/{$document->id}/download")->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'download', 'target_id' => $document->id, 'owner_id' => $current->id]);
    }

    public function test_a_site_photo_preview_is_refused_for_another_owners_parcel(): void
    {
        Storage::fake(ParcelPhoto::PRIVATE_DISK);
        [$former, $current, $parcel] = $this->parcelThatChangedHands();
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('site/a.jpg', 'jpg');
        $photo = ParcelPhoto::create([
            'parcel_id' => $parcel->id, 'photo_url' => 'site/a.jpg', 'storage_disk' => ParcelPhoto::PRIVATE_DISK,
            'photo_type' => PhotoType::Ground->value,
        ]);

        $this->actingAs($former, 'owner')->get(route('portal.documents.preview', $photo))->assertNotFound();
        $this->actingAs($current, 'owner')->get(route('portal.documents.preview', $photo))->assertOk();
    }

    public function test_legacy_public_documents_move_to_the_private_disk(): void
    {
        Storage::fake(ParcelPhoto::LEGACY_DISK);
        Storage::fake(ParcelPhoto::PRIVATE_DISK);
        [, , $parcel] = $this->parcelThatChangedHands();
        Storage::disk(ParcelPhoto::LEGACY_DISK)->put('documents/deeds/123456789012.pdf', 'deed');
        Storage::disk(ParcelPhoto::LEGACY_DISK)->put('documents/deeds/orphan.pdf', 'orphan');
        $row = ParcelPhoto::create([
            'parcel_id' => $parcel->id, 'photo_url' => '/storage/documents/deeds/123456789012.pdf',
            'photo_type' => PhotoType::Deed->value,
        ]);

        $this->artisan('documents:secure-legacy', ['--dry-run' => true])->assertSuccessful();
        Storage::disk(ParcelPhoto::LEGACY_DISK)->assertExists('documents/deeds/123456789012.pdf');

        $this->artisan('documents:secure-legacy')->assertSuccessful();

        Storage::disk(ParcelPhoto::LEGACY_DISK)->assertMissing('documents/deeds/123456789012.pdf');
        Storage::disk(ParcelPhoto::LEGACY_DISK)->assertMissing('documents/deeds/orphan.pdf');
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->assertExists('documents/deeds/123456789012.pdf');
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->assertExists('documents/deeds/orphan.pdf');
        $this->assertSame(ParcelPhoto::PRIVATE_DISK, $row->fresh()->storage_disk);
        $this->assertSame('documents/deeds/123456789012.pdf', $row->fresh()->photo_url);
    }

    public function test_responses_carry_the_browser_hardening_headers(): void
    {
        $this->get(route('portal.login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_audit_log_entries_cannot_be_changed_or_removed(): void
    {
        $entry = AuditLog::create(['action' => 'download', 'target_type' => 'document', 'target_id' => 1]);

        $this->expectException(LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    /** @return array{0: Owner, 1: Owner, 2: Parcel} the earlier owner, the present one and the parcel */
    private function parcelThatChangedHands(): array
    {
        $former = Owner::create(['name' => 'المالك السابق', 'national_id' => '1000000011', 'phone' => '0500000011']);
        $current = Owner::create(['name' => 'المالك الحالي', 'national_id' => '1000000012', 'phone' => '0500000012']);
        $parcel = Parcel::create(['parcel_no' => '77', 'geo_id' => 'geo-77', 'plan_id' => Plan::create(['plan_no' => '9'])->id]);

        Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'old', 'deed_area' => 500, 'deed_status' => DeedStatus::Old->value])
            ->owners()->attach($former->id);
        Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'new', 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value])
            ->owners()->attach($current->id);

        return [$former, $current, $parcel];
    }
}
