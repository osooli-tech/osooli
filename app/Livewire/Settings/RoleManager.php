<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManager extends Component
{
    /** Permissions grouped for display */
    private const GROUPS = [
        'parcels' => ['parcels.view', 'parcels.view_map'],
        'documents' => ['documents.download'],
        'exports' => ['exports.create'],
        'requests' => ['modification_requests.view', 'modification_requests.manage'],
        'users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
        'admin' => ['roles.manage', 'audit_logs.view', 'sync.view'],
    ];

    // Create modal
    public bool $showCreateModal = false;

    public string $newRoleName = '';

    // Edit permissions modal
    public bool $showEditModal = false;

    public ?int $editingRoleId = null;

    public string $editingRoleName = '';

    /** @var array<string, bool> */
    public array $permissionChecks = [];

    // Delete (confirmed via SweetAlert on the frontend — no server-side modal state needed)
    public ?int $deletingRoleId = null;

    public function openCreate(): void
    {
        $this->newRoleName = '';
        $this->resetValidation('newRoleName');
        $this->showCreateModal = true;
    }

    public function createRole(): void
    {
        $this->validate(
            ['newRoleName' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')]],
            [],
            ['newRoleName' => __('settings.role_name')]
        );

        Role::create(['name' => $this->newRoleName, 'guard_name' => 'web']);
        Cache::flush();

        $this->showCreateModal = false;
        $this->newRoleName = '';
        $this->dispatch('toast', type: 'success', message: __('settings.role_created'));
    }

    public function openEdit(int $roleId): void
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->editingRoleId = $roleId;
        $this->editingRoleName = $role->name;

        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $this->permissionChecks = [];
        foreach (Permission::all()->pluck('name') as $perm) {
            // Replace dots with __ so Livewire wire:model doesn't treat them as
            // nested-array separators. All permission names have exactly one dot
            // and no double-underscore, so __ is a safe delimiter.
            $safeKey = str_replace('.', '__', $perm);
            $this->permissionChecks[$safeKey] = in_array($perm, $rolePermissions, true);
        }

        $this->showEditModal = true;
    }

    public function savePermissions(): void
    {
        if ($this->editingRoleId === null) {
            return;
        }

        $role = Role::findOrFail($this->editingRoleId);
        // Restore original permission names (undo the dot→underscore safe-key replacement)
        $grantedSafeKeys = array_keys(array_filter($this->permissionChecks));
        $granted = array_map(static fn (string $k) => str_replace('__', '.', $k), $grantedSafeKeys);
        $permissions = Permission::whereIn('name', $granted)->get();
        $role->syncPermissions($permissions);
        Cache::flush();

        $this->showEditModal = false;
        $this->editingRoleId = null;
        $this->dispatch('toast', type: 'success', message: __('settings.permissions_saved'));
    }

    #[On('deleteRole')]
    public function deleteRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        if ($role->users()->count() > 0) {
            $this->dispatch('toast', type: 'error', message: __('settings.role_has_users'));

            return;
        }

        $role->delete();
        Cache::flush();

        $this->deletingRoleId = null;
        $this->dispatch('toast', type: 'success', message: __('settings.role_deleted'));
    }

    /** @return array<string, list<string>> */
    public function groups(): array
    {
        return self::GROUPS;
    }

    public function render(): View
    {
        return view('livewire.settings.role-manager', [
            'roles' => Role::withCount('users')->with('permissions')->orderBy('name')->get(),
            'groups' => self::GROUPS,
        ]);
    }
}
