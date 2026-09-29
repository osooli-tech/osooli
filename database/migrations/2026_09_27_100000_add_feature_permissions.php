<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A permission of its own for each feature added lately, so a role can be
 * given or refused it on the roles screen.
 *
 * Each is granted to every role that already holds the permission the
 * feature was checked against until now, so nobody loses anything the day
 * this runs — and roles edited by hand keep their edits: this adds, it never
 * resets (as re-running RolesAndPermissionsSeeder would).
 */
return new class extends Migration
{
    /** New permission => the permission a role must hold to be given it ('*' for every role). */
    private const GRANTS = [
        'help.view' => '*',
        'boundaries.view' => 'parcels.view_map',
        'boundaries.edit' => 'reference.edit',
        'parcels.placement' => 'parcels.view',
        'map_layers.view' => 'parcels.view_map',
        'map_layers.manage' => 'imports.create',
        'documents.split' => 'documents.upload',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::GRANTS as $name => $from) {
            $permission = Permission::findOrCreate($name, 'web');

            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if ($from === '*' || $role->hasPermissionTo($from)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::GRANTS))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
