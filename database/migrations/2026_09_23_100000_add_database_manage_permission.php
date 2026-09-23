<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The database settings page — connections, primary database, sync — is
 * gated on its own permission, held by super_admin only. Granted here as
 * well as in the seeder so a live installation gets it with `migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'database.manage', 'guard_name' => 'web']);

        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);

        $this->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'database.manage')->where('guard_name', 'web')->delete();

        $this->forgetCachedPermissions();
    }

    /**
     * The permission cache lives on the application's cache store, which is
     * not necessarily the database being migrated — a sync migrates the
     * secondary while the cache sits on the primary. Failing to clear it must
     * not fail the migration; a sync empties the target's cache anyway.
     */
    private function forgetCachedPermissions(): void
    {
        rescue(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(), report: false);
    }
};
