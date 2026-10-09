<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserIndex extends Component
{
    use FiltersByCreatedAt;
    use WithPagination;

    public string $search = '';

    public string $filterRole = '';

    // Modal
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form
    public string $formName = '';

    public string $formEmail = '';

    public string $formPhone = '';

    public string $formPassword = '';

    public string $formRole = '';

    /** @var array<string> */
    public array $availableRoles = [];

    public function mount(): void
    {
        $this->mustBeAbleTo('users.view');
        $this->availableRoles = Role::query()->orderBy('name')->pluck('name')->toArray();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterRole', 'createdFrom', 'createdTo']);
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->mustBeAbleTo('users.create');
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $userId): void
    {
        $this->mustBeAbleTo('users.edit');
        $user = User::with('roles')->findOrFail($userId);
        $this->editingId = $userId;
        $this->formName = $user->name;
        $this->formEmail = $user->email;
        $this->formPhone = $user->phone ?? '';
        $this->formPassword = '';
        $this->formRole = $user->getRoleNames()->first() ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        // The buttons are hidden by permission, but a Livewire action can be
        // called directly — so each one is checked here as well.
        $this->mustBeAbleTo($this->editingId === null ? 'users.create' : 'users.edit');
        $rules = [
            'formName' => ['required', 'string', 'max:255'],
            'formEmail' => ['required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId)],
            'formPhone' => ['nullable', 'string', 'max:30'],
            'formRole' => ['required', 'string', Rule::exists('roles', 'name')],
        ];

        if ($this->editingId === null) {
            $rules['formPassword'] = ['required', 'string', 'min:8'];
        } else {
            $rules['formPassword'] = ['nullable', 'string', 'min:8'];
        }

        $this->validate($rules, [], [
            'formName' => __('users.name'),
            'formEmail' => __('users.email'),
            'formPhone' => __('users.phone'),
            'formPassword' => __('users.password'),
            'formRole' => __('users.role'),
        ]);

        $this->mustBeAllowedToGrant($this->formRole);

        if ($this->editingId === null) {
            $user = User::create([
                'name' => $this->formName,
                'email' => $this->formEmail,
                'phone' => $this->formPhone ?: null,
                'password' => Hash::make($this->formPassword),
                'is_active' => true,
            ]);
            $user->syncRoles([$this->formRole]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_user',
                'target_type' => 'user',
                'target_id' => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } else {
            $user = User::findOrFail($this->editingId);
            $data = [
                'name' => $this->formName,
                'email' => $this->formEmail,
                'phone' => $this->formPhone ?: null,
            ];
            if ($this->formPassword !== '') {
                $data['password'] = Hash::make($this->formPassword);
            }
            $user->update($data);
            $user->syncRoles([$this->formRole]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'edit_user',
                'target_type' => 'user',
                'target_id' => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: __('users.saved'));
    }

    public function toggleActive(int $userId): void
    {
        $this->mustBeAbleTo('users.edit');
        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $user->is_active ? 'activate_user' : 'deactivate_user',
            'target_type' => 'user',
            'target_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingId = $userId;
        $this->showDeleteConfirm = true;
    }

    public function deleteUser(): void
    {
        $this->mustBeAbleTo('users.delete');
        if ($this->deletingId === null) {
            return;
        }

        // Prevent self-deletion
        if ($this->deletingId === Auth::id()) {
            $this->showDeleteConfirm = false;
            $this->deletingId = null;
            $this->dispatch('toast', type: 'error', message: __('users.cannot_delete_self'));

            return;
        }

        $user = User::findOrFail($this->deletingId);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete_user',
            'target_type' => 'user',
            'target_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $user->delete();

        $this->showDeleteConfirm = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: __('users.deleted'));
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deletingId = null;
    }

    private function resetForm(): void
    {
        $this->formName = '';
        $this->formEmail = '';
        $this->formPhone = '';
        $this->formPassword = '';
        $this->formRole = '';
        $this->resetValidation();
    }

    /** @return LengthAwarePaginator<User> */
    private function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q2) use ($term): void {
                    $q2->whereLike('name', $term)
                        ->orWhereLike('email', $term)
                        ->orWhereLike('phone', $term);
                });
            })
            ->when($this->filterRole !== '', fn ($q) => $q->role($this->filterRole))
            ->latest()
            ->tap(fn ($q) => $this->applyCreatedAt($q, 'users.created_at'))
            ->paginate(20);
    }

    private function mustBeAbleTo(string $permission): void
    {
        abort_unless(Auth::user()?->can($permission) === true, 403);
    }

    /**
     * Nobody hands out more than they hold: a role may only be given by
     * someone who already has every permission in it. Otherwise editing
     * users would be a way to make oneself super_admin.
     */
    private function mustBeAllowedToGrant(string $roleName): void
    {
        $actor = Auth::user();
        $needed = Role::findByName($roleName)->permissions->pluck('name');

        abort_unless($actor !== null && $needed->every(fn (string $p): bool => $actor->can($p)), 403);
    }

    public function render(): View
    {
        return view('livewire.users.user-index', [
            'users' => $this->users(),
        ]);
    }
}
