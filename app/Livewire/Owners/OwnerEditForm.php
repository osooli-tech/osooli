<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Models\Owner;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lets staff correct an owner's officially-sourced contact fields (name,
 * national ID, phone) directly from the dashboard — the mobile API keeps
 * these read-only for the owner themselves (see UpdateProfileRequest),
 * since a source-data error (seen firsthand with a GDB import this project
 * carries) can only be fixed here.
 */
class OwnerEditForm extends Component
{
    #[Locked]
    public int $ownerId;

    public bool $editing = false;

    public string $name = '';

    public string $nationalId = '';

    public string $phone = '';

    public function mount(int $ownerId): void
    {
        // The blade only *includes* this component behind @can('owners.edit')
        // — that hides the markup but does not stop a request crafted
        // directly at this component's actions, so authorization has to be
        // enforced here too, not just at the call site.
        abort_unless(auth()->user()?->can('owners.edit'), 403);

        $this->ownerId = $ownerId;
        $this->fillFromOwner();
    }

    public function edit(): void
    {
        $this->fillFromOwner();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->fillFromOwner();
        $this->editing = false;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('owners.edit'), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nationalId' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
        ], [], [
            'name' => __('owners.name'),
            'nationalId' => __('owners.national_id'),
            'phone' => __('owners.phone'),
        ]);

        $this->owner()->update([
            'name' => $validated['name'],
            'national_id' => $validated['nationalId'] ?: null,
            'phone' => $validated['phone'] ?: null,
        ]);

        $this->editing = false;
        $this->dispatch('owner-updated');
    }

    public function render(): View
    {
        return view('livewire.owners.owner-edit-form', [
            'owner' => $this->owner(),
        ]);
    }

    private function fillFromOwner(): void
    {
        $owner = $this->owner();
        $this->name = $owner->name;
        $this->nationalId = $owner->national_id ?? '';
        $this->phone = $owner->phone ?? '';
    }

    private function owner(): Owner
    {
        return Owner::findOrFail($this->ownerId);
    }
}
