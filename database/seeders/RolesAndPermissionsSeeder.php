<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    private const PERMISSIONS = [
        'parcels.view',
        'parcels.view_map',
        'parcels.create',
        'parcels.edit',
        'parcels.edit_geometry',
        'parcels.archive',
        'deeds.create',
        'deeds.edit',
        'deeds.archive',
        'survey_decisions.edit',
        'ownership.manage',
        'reference.view',
        'reference.create',
        'reference.edit',
        'reference.delete',
        'archive.view',
        'archive.restore',
        'documents.download',
        'documents.upload',
        'documents.review',
        'exports.create',
        'owners.create',
        'owners.edit',
        'modification_requests.view',
        'modification_requests.manage',
        'presentation_requests.view',
        'users.view',
        'users.create',
        'users.edit',
        'users.delete',
        'roles.manage',
        'audit_logs.view',
        'sync.view',
    ];

    private const ROLES = [
        'super_admin' => null,                  // كل الصلاحيات
        'manager' => ['except' => ['roles.manage']],

        /*
         * The engineer role is the surveying side of the work: it may correct
         * a parcel, its boundaries and its survey decisions, and read the
         * reference lists those screens pick from. Ownership stays out — who
         * owns what is a registry decision, not a surveying one — as does
         * creating reference records, which changes data every other screen
         * depends on.
         *
         * It may add a document — a surveyor comes back from the field with
         * the paperwork — but not approve one. Deciding that a deed scan is
         * genuine is the manager's call, and letting the uploader also be the
         * reviewer would make the review queue decorative.
         */
        'engineer' => ['only' => [
            'parcels.view',
            'parcels.view_map',
            'parcels.edit',
            'parcels.edit_geometry',
            'survey_decisions.edit',
            'reference.view',
            'documents.download',
            'documents.upload',
            'exports.create',
            'modification_requests.view',
        ]],
    ];

    public function run(): void
    {
        Cache::flush();

        // 1. الصلاحيات
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $all = Permission::whereIn('name', self::PERMISSIONS)->get();

        // 2. الأدوار
        foreach (self::ROLES as $roleName => $config) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($config === null) {
                $role->syncPermissions($all);
            } elseif (isset($config['except'])) {
                $role->syncPermissions($all->whereNotIn('name', $config['except']));
            } elseif (isset($config['only'])) {
                $role->syncPermissions($all->whereIn('name', $config['only']));
            }
        }

        // 3. Super Admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@sakuki.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );

        $user->assignRole('super_admin');

        Cache::flush();

        $this->command->info('✓ Permissions: '.count(self::PERMISSIONS));
        $this->command->info('✓ Roles: '.implode(', ', array_keys(self::ROLES)));
        $this->command->info('✓ User: admin@sakuki.test / password');
    }
}
