<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reassigning a misplaced parcel's district from «قطع خارج حيّها»: it moves
 * a plan to another district, or a district to another city — an edit to
 * reference data — so it goes to every role that may already edit that.
 * Adds only; roles edited by hand keep their edits.
 */
return new class extends Migration
{
    private const NAME = 'parcels.placement_fix';

    private const FROM = 'reference.edit';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::NAME, 'web');

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            if (Permission::query()->where('name', self::FROM)->exists() && $role->hasPermissionTo(self::FROM)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::NAME)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
