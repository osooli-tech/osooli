<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Owners\OwnerEditForm;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OwnerEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_without_the_permission_is_rejected(): void
    {
        $owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $user = User::create([
            'name' => 'مهندس',
            'email' => 'engineer@sakuki.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(OwnerEditForm::class, ['ownerId' => $owner->id])
            ->assertStatus(403);
    }

    public function test_an_authorised_user_can_correct_the_owners_name_and_phone(): void
    {
        $owner = Owner::create(['name' => 'اسم قديم غلط', 'national_id' => '1000000001', 'phone' => '0512345678']);
        $user = $this->editor();

        Livewire::actingAs($user)
            ->test(OwnerEditForm::class, ['ownerId' => $owner->id])
            ->call('edit')
            ->set('name', 'الاسم الصحيح')
            ->set('phone', '0598765432')
            ->call('save')
            ->assertSet('editing', false)
            ->assertDispatched('owner-updated');

        $owner->refresh();
        $this->assertSame('الاسم الصحيح', $owner->name);
        $this->assertSame('0598765432', $owner->phone);
    }

    public function test_an_invalid_phone_is_rejected(): void
    {
        $owner = Owner::create(['name' => 'مالك تجريبي']);
        $user = $this->editor();

        Livewire::actingAs($user)
            ->test(OwnerEditForm::class, ['ownerId' => $owner->id])
            ->call('edit')
            ->set('phone', 'not-a-phone')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    private function editor(): User
    {
        $user = User::create([
            'name' => 'مدير',
            'email' => 'manager@sakuki.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        Permission::findOrCreate('owners.edit', 'web');
        $user->givePermissionTo('owners.edit');

        return $user;
    }
}
