<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The bulk export page hands out every owner's national ID and phone number
 * in one file, so it has its own permission rather than riding on
 * exports.create. Given to super_admin and manager, as the seeder does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'exports.bulk', 'guard_name' => 'web']);

        Role::whereIn('name', ['super_admin', 'manager'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }

    public function down(): void
    {
        Permission::where('name', 'exports.bulk')->where('guard_name', 'web')->delete();

        rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }
};
