<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may see and change which phones open which owners in the portal: every
 * role that may already edit owners — the same people who set an owner's
 * phone today. Adds only; roles edited by hand keep their edits.
 */
return new class extends Migration
{
    /** @var array<string, string> new permission => the one that brings it */
    private const PERMISSIONS = [
        'portal_accounts.view' => 'owners.edit',
        'portal_accounts.manage' => 'owners.edit',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $from) {
            $permission = Permission::findOrCreate($name, 'web');

            if (! Permission::query()->where('name', $from)->where('guard_name', 'web')->exists()) {
                continue;
            }

            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if ($role->hasPermissionTo($from)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
