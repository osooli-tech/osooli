<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Livewire\Forms\OwnerForm;
use App\Models\Owner;
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

        try {
            $owner = $editing ? $this->form->update() : $this->form->store();
        } catch (RuntimeException $e) {
            // The optimistic lock refusing the write, not a crash: someone
            // changed this owner between the form opening and this click.
            $this->conflict = $e->getMessage();

            return;
        }

        $this->showModal = false;

        $this->dispatch('owner-saved', ownerId: $owner->id);
        $this->dispatch('toast', type: 'success',
            message: __($editing ? 'common.updated' : 'common.created'));
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
    }

    public function render(): View
    {
        return view('livewire.owners.owner-form-modal');
    }
}
