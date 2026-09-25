<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Importing writes deeds, parcels and owners in bulk, so it has its own
 * permission, given to super_admin and manager as the seeder does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'imports.run', 'guard_name' => 'web']);

        Role::whereIn('name', ['super_admin', 'manager'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }

    public function down(): void
    {
        Permission::where('name', 'imports.run')->where('guard_name', 'web')->delete();

        rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }
};
