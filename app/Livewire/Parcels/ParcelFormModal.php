<?php

declare(strict_types=1);

namespace App\Livewire\Parcels;

use App\Livewire\Forms\DeedForm;
use App\Livewire\Forms\ParcelForm;
use App\Models\Deed;
use App\Models\Parcel;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * The parcel editor, and the deed editor that hangs off it.
 *
 * Both live in one component because a deed only ever exists for a parcel: the
 * deed dialog is reached from the parcel it belongs to, and closing it returns
 * there. Any list screen can open either by dispatching `parcel-create`,
 * `parcel-edit`, `deed-create` or `deed-edit`.
 */
class ParcelFormModal extends Component
{
    public ParcelForm $form;

    public DeedForm $deedForm;

    public bool $showParcelModal = false;

    public bool $showDeedModal = false;

    /** Whether closing the deed dialog should hand the parcel dialog back. */
    public bool $resumeParcelModal = false;

    /** @var array<int, string> */
    public array $planOptions = [];

    /** @var array<int, string> */
    public array $parentParcelOptions = [];

    #[On('parcel-create')]
    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('parcels.create'), 403);

        $this->reset(['showDeedModal', 'resumeParcelModal']);
        $this->form->reset();
        $this->resetValidation();
        $this->loadOptions(null);
        $this->showParcelModal = true;
    }

    #[On('parcel-edit')]
    public function openEdit(int $parcelId): void
    {
        abort_unless(auth()->user()?->can('parcels.edit'), 403);

        $this->reset(['showDeedModal', 'resumeParcelModal']);
        $this->resetValidation();

        $parcel = Parcel::findOrFail($parcelId);
        $this->form->setParcel($parcel);
        $this->loadOptions($parcel->id);
        $this->showParcelModal = true;
    }

    public function save(): void
    {
        $creating = $this->form->parcelId === null;

        // The list screen hides the button behind @can, which stops nothing:
        // a request can be aimed straight at this action, so the check has to
        // live here as well as at the call site.
        abort_unless(auth()->user()?->can($creating ? 'parcels.create' : 'parcels.edit'), 403);

        try {
            $parcel = $creating ? $this->form->store() : $this->form->update();
        } catch (RuntimeException $exception) {
            $this->reportConflict($exception);

            return;
        }

        $this->showParcelModal = false;
        $this->dispatch('toast', type: 'success', message: __($creating ? 'common.created' : 'common.updated'));
        $this->dispatch('parcel-saved', parcelId: $parcel->id);
    }

    public function closeParcel(): void
    {
        $this->showParcelModal = false;
        $this->resetValidation();
    }

    /** Add a deed to the parcel currently open in the parcel dialog. */
    public function addDeed(): void
    {
        if ($this->form->parcelId === null) {
            return;
        }

        $this->resumeParcelModal = $this->showParcelModal;
        $this->createDeed($this->form->parcelId);
    }

    #[On('deed-create')]
    public function createDeed(int $parcelId): void
    {
        abort_unless(auth()->user()?->can('deeds.create'), 403);

        $this->resetValidation();
        $this->deedForm->setParcel(Parcel::findOrFail($parcelId));
        $this->showParcelModal = false;
        $this->showDeedModal = true;
    }

    #[On('deed-edit')]
    public function editDeed(int $deedId): void
    {
        abort_unless(auth()->user()?->can('deeds.edit'), 403);

        $this->resetValidation();
        $this->resumeParcelModal = $this->showParcelModal;
        $this->deedForm->setDeed(Deed::findOrFail($deedId));
        $this->showParcelModal = false;
        $this->showDeedModal = true;
    }

    public function saveDeed(): void
    {
        $creating = $this->deedForm->deedId === null;

        abort_unless(auth()->user()?->can($creating ? 'deeds.create' : 'deeds.edit'), 403);

        try {
            $deed = $creating ? $this->deedForm->store() : $this->deedForm->update();
        } catch (RuntimeException $exception) {
            $this->reportConflict($exception);

            return;
        }

        $this->dispatch('toast', type: 'success', message: __($creating ? 'common.created' : 'common.updated'));
        $this->dispatch('deed-saved', deedId: $deed->id);
        $this->closeDeed();
    }

    public function closeDeed(): void
    {
        $this->showDeedModal = false;
        $this->resetValidation();

        // Hand the parcel dialog back if the deed was opened from inside it,
        // so an edit of several deeds in a row does not cost a reopen each.
        if ($this->resumeParcelModal) {
            $this->showParcelModal = true;
            $this->resumeParcelModal = false;
        }
    }

    public function render(): View
    {
        return view('livewire.parcels.parcel-form-modal', [
            'deeds' => $this->deeds(),
        ]);
    }

    /**
     * Only an optimistic-lock failure is meant to reach the user.
     *
     * WritesSafely signals a conflict with a plain RuntimeException carrying
     * the translated message, and a QueryException is a RuntimeException too —
     * putting that in a toast would show raw SQL. Anything else is a bug and
     * belongs in the error handler, not in a notification.
     */
    private function reportConflict(RuntimeException $exception): void
    {
        if ($exception->getMessage() !== __('common.conflict')) {
            throw $exception;
        }

        $this->dispatch('toast', type: 'error', message: $exception->getMessage());
    }

    /**
     * Contents of the two foreign-key dropdowns.
     *
     * Parent candidates are top-level parcels only, minus the parcel being
     * edited: the hierarchy in use is one level deep — sub-units are the
     * apartments inside a building — and offering a parcel its own id or one
     * of its descendants would build a cycle the FK cannot refuse.
     */
    private function loadOptions(?int $editingParcelId): void
    {
        $this->planOptions = Plan::query()
            ->orderBy('plan_no')
            ->get(['id', 'plan_no'])
            ->mapWithKeys(fn (Plan $plan): array => [$plan->id => (string) $plan->plan_no])
            ->all();

        $this->parentParcelOptions = Parcel::query()
            ->whereNull('parent_parcel_id')
            ->when($editingParcelId !== null, fn ($query) => $query->whereKeyNot($editingParcelId))
            ->orderBy('parcel_no')
            ->get(['id', 'parcel_no', 'geo_id'])
            ->mapWithKeys(fn (Parcel $parcel): array => [$parcel->id => $this->parcelLabel($parcel)])
            ->all();
    }

    /** `parcel_no` is nullable, so a parcel without one falls back to its GEO id. */
    private function parcelLabel(Parcel $parcel): string
    {
        $parcelNo = trim((string) $parcel->parcel_no);

        return $parcelNo !== '' ? $parcelNo : (string) $parcel->geo_id;
    }

    /**
     * Every deed of the parcel currently open, newest first.
     *
     * @return Collection<int, Deed>
     */
    private function deeds(): Collection
    {
        if ($this->form->parcelId === null) {
            return new Collection;
        }

        return Deed::query()
            ->where('parcel_id', $this->form->parcelId)
            ->orderByDesc('id')
            ->get();
    }
}
