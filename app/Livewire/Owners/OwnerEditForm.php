<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Models\Owner;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Closure;
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

    /** @var list<string> owners the new phone already opens, awaiting a yes */
    public array $sharedWith = [];

    public bool $sharedConfirmed = false;

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
            'nationalId' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (Owner::nationalIdTaken((string) $value, $this->owner()->getKey())) {
                    $fail(__('validation.unique', ['attribute' => __('owners.national_id')]));
                }
            }],
            'phone' => ['nullable', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
        ], [], [
            'name' => __('owners.name'),
            'nationalId' => __('owners.national_id'),
            'phone' => __('owners.phone'),
        ]);

        $owner = $this->owner();
        $phone = (string) ($validated['phone'] ?? '');
        $phoneChanged = Owner::normalisePhone($phone) !== Owner::normalisePhone((string) $owner->phone);

        // The phone signs in to the portal: if it already opens other owners,
        // say so and wait for a yes before this owner joins them.
        if ($phoneChanged && $phone !== '' && ! $this->sharedConfirmed) {
            $this->sharedWith = app(PortalAccessService::class)->ownersSharingPhone($phone, (int) $owner->id)->pluck('name')->all();

            if ($this->sharedWith !== []) {
                return;
            }
        }

        $owner->update([
            'name' => $validated['name'],
            'national_id' => $validated['nationalId'] ?: null,
        ]);

        if ($phoneChanged) {
            app(PortalAccessService::class)->changeOwnerPhone($owner, $phone === '' ? null : $phone, $this->staff());
        }

        $this->sharedWith = [];
        $this->sharedConfirmed = false;
        $this->editing = false;
        $this->dispatch('owner-updated');
    }

    /** Saves the phone after staff agreed it may open this owner beside the others. */
    public function confirmSharedPhone(): void
    {
        $this->sharedConfirmed = true;
        $this->save();
    }

    private function staff(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
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
