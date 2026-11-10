<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Livewire\Forms\OwnerForm;
use App\Models\Owner;
use App\Services\Portal\PortalAccessService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Creating an owner, and editing all five of their fields.
 *
 * Replaces the inline editor that covered three: `email` and `whatsapp` were
 * columns the dashboard displayed but could never change. Creating an owner
 * had no screen at all — owners could only arrive through an import.
 *
 * One instance serves the whole list: it listens on Livewire's event bus, so
 * the rows dispatch to it rather than each carrying a form of their own.
 */
class OwnerFormModal extends Component
{
    public OwnerForm $form;

    public bool $showModal = false;

    /** Set when the optimistic lock trips, shown above the fields. */
    public string $conflict = '';

    /** @var list<string> owners the phone already opens, awaiting a yes */
    public array $sharedWith = [];

    public bool $sharedConfirmed = false;

    #[On('owner-create')]
    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('owners.create'), 403);

        $this->resetForm();
        $this->showModal = true;
    }

    #[On('owner-edit')]
    public function openEdit(int $ownerId): void
    {
        abort_unless(auth()->user()?->can('owners.edit'), 403);

        $this->resetForm();
        $this->form->setOwner(Owner::findOrFail($ownerId));
        $this->showModal = true;
    }

    public function save(): void
    {
        $editing = $this->form->ownerId !== null;

        abort_unless(
            auth()->user()?->can($editing ? 'owners.edit' : 'owners.create'),
            403
        );

        $this->conflict = '';

        // The phone signs in to the portal: if it already opens other owners,
        // name them and wait for a yes before this owner joins them.
        if (! $this->sharedConfirmed) {
            $this->sharedWith = $this->ownersThePhoneOpens();

            if ($this->sharedWith !== []) {
                return;
            }
        }

        try {
            $owner = $editing ? $this->form->update() : $this->form->store();
        } catch (RuntimeException $e) {
            // The optimistic lock refusing the write, not a crash: someone
            // changed this owner between the form opening and this click.
            $this->conflict = $e->getMessage();

            return;
        }

        $this->showModal = false;
        $this->sharedWith = [];
        $this->sharedConfirmed = false;

        $this->dispatch('owner-saved', ownerId: $owner->id);
        $this->dispatch('toast', type: 'success',
            message: __($editing ? 'common.updated' : 'common.created'));
    }

    /** Saves after staff agreed the phone may open this owner beside the others. */
    public function confirmSharedPhone(): void
    {
        $this->sharedConfirmed = true;
        $this->save();
    }

    /**
     * Other owners the form's phone already opens, when the phone is new for
     * this owner; empty when there is nothing to ask.
     *
     * @return list<string>
     */
    private function ownersThePhoneOpens(): array
    {
        $phone = trim($this->form->phone);

        if ($phone === '') {
            return [];
        }

        $current = $this->form->ownerId === null ? null : Owner::find($this->form->ownerId)?->phone;

        if (Owner::normalisePhone($phone) === Owner::normalisePhone((string) $current)) {
            return [];
        }

        return app(PortalAccessService::class)
            ->ownersSharingPhone($phone, $this->form->ownerId)
            ->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->values()
            ->all();
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form->reset();
        $this->resetErrorBag();
        $this->conflict = '';
        $this->sharedWith = [];
        $this->sharedConfirmed = false;
    }

    public function render(): View
    {
        return view('livewire.owners.owner-form-modal');
    }
}
