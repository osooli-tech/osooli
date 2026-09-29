<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Settings\RoleManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The permissions given to the features added lately: granted on migration
 * to the roles that held the permission each feature used before, offered
 * on the roles screen, and enforced on each feature's pages.
 */
class FeaturePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_grants_each_to_the_roles_that_had_its_old_permission(): void
    {
        foreach (['reference.edit', 'documents.upload', 'imports.create', 'parcels.view', 'parcels.view_map'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $editor = Role::findOrCreate('editor', 'web');
        $editor->givePermissionTo(['reference.edit', 'documents.upload']);
        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->givePermissionTo(['parcels.view', 'parcels.view_map']);

        $migration = require database_path('migrations/2026_09_27_100000_add_feature_permissions.php');
        $migration->up();

        $this->assertTrue($editor->fresh()->hasPermissionTo('boundaries.edit'));
        $this->assertTrue($editor->fresh()->hasPermissionTo('documents.split'));
        $this->assertFalse($editor->fresh()->hasPermissionTo('map_layers.manage'));
        $this->assertTrue($viewer->fresh()->hasPermissionTo('parcels.placement'));
        $this->assertTrue($viewer->fresh()->hasPermissionTo('boundaries.view'));
        $this->assertTrue($viewer->fresh()->hasPermissionTo('map_layers.view'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('boundaries.edit'));
        // Everyone sees the page explanations.
        $this->assertTrue($editor->fresh()->hasPermissionTo('help.view'));
        $this->assertTrue($viewer->fresh()->hasPermissionTo('help.view'));
    }

    public function test_each_is_on_the_roles_screen_and_guards_its_pages(): void
    {
        $migration = require database_path('migrations/2026_09_27_100000_add_feature_permissions.php');
        $migration->up();
        Permission::findOrCreate('roles.manage', 'web');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('roles.manage');
        $this->actingAs($admin);
        app()->setLocale('ar');

        $role = Role::findOrCreate('editor', 'web');

        Livewire::test(RoleManager::class)
            ->call('openEdit', $role->id)
            ->assertSee('الخريطة والحدود والطبقات')
            ->assertSee('عرض شرح الصفحات')
            ->assertSee('رسم الحدود الإدارية وتعديلها')
            ->assertSee('إدارة الطبقات المخصصة')
            ->assertSee('رفع ملف PDF متعدد القطع');

        $plain = User::factory()->create(['is_active' => true]);
        foreach (['parcels.placement', 'documents.split', 'map-layers.index'] as $route) {
            $this->actingAs($plain)->get(route($route))->assertForbidden();
        }
        $this->actingAs($plain)->getJson(route('geo.boundaries', 'districts'))->assertForbidden();
    }
}
