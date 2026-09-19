<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Livewire\Forms\DocumentUploadForm;
use App\Models\Deed;
use App\Models\Parcel;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * The first place in this application where a file can arrive from a browser.
 *
 * Everything it accepts goes to the private `documents` disk and starts
 * pending, so an upload cannot put a document in front of an owner until a
 * reviewer has looked at it. Which parcel a document may be filed against is
 * decided here and re-checked on save, because the parcel id travels to the
 * client with the rest of the form state.
 */
class DocumentUpload extends Component
{
    use WithFileUploads;

    public DocumentUploadForm $form;

    /** True when the screen was opened from one parcel and may not leave it. */
    #[Locked]
    public bool $parcelLocked = false;

    public function mount(?int $parcelId = null, ?int $deedId = null): void
    {
        abort_unless(auth()->user()?->can('documents.upload'), 403);

        if ($parcelId === null) {
            return;
        }

        abort_unless(OwnerScope::canSeeParcel($this->currentUser(), $parcelId), 403);

        $this->form->parcelId = (string) $parcelId;
        $this->form->deedId = (string) $deedId;
        $this->parcelLocked = true;
    }

    /** A deed belongs to one parcel, so changing the parcel invalidates it. */
    public function updatedFormParcelId(): void
    {
        $this->form->deedId = '';
    }

    public function removeFile(int $index): void
    {
        $this->form->forget($index);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('documents.upload'), 403);

        // The form's `exists` rule proves the parcel is real, not that this
        // user may file anything against it — a restricted user must stay
        // inside their own owners' parcels here as everywhere else.
        $parcelId = $this->form->parcel();

        abort_unless(
            $parcelId === null || OwnerScope::canSeeParcel($this->currentUser(), $parcelId),
            403
        );

        try {
            $documents = $this->form->store();
        } catch (RuntimeException $exception) {
            // Writing to disk failed — a full volume or a permissions problem.
            // The user can retry, so it is a toast rather than an error page.
            if ($exception::class !== RuntimeException::class) {
                throw $exception;
            }

            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->form->deedId = '';
        $this->resetValidation();

        $this->dispatch('documents-uploaded');
        $this->dispatch('toast', type: 'success', message: __('documents.uploaded', [
            'count' => (string) count($documents),
        ]));
    }

    public function render(): View
    {
        return view('livewire.documents.document-upload', [
            'parcelOptions' => $this->parcelOptions(),
            'deedOptions' => $this->deedOptions(),
            'maxMegabytes' => (int) round(DocumentUploadForm::MAX_KILOBYTES / 1024),
            'maxFiles' => DocumentUploadForm::MAX_FILES,
            'acceptAttribute' => '.'.implode(',.', DocumentUploadForm::ACCEPTED_EXTENSIONS),
        ]);
    }

    /**
     * Parcels this user may file a document against, as id => parcel number.
     *
     * @return array<int, string>
     */
    private function parcelOptions(): array
    {
        $chosen = $this->form->parcel();

        if ($this->parcelLocked && $chosen !== null) {
            return Parcel::query()
                ->whereKey($chosen)
                ->pluck('parcel_no', 'id')
                ->all();
        }

        $allowed = OwnerScope::parcelIds($this->currentUser());

        return Parcel::query()
            ->when($allowed !== null, fn (Builder $query) => $query->whereIn('id', $allowed ?? []))
            ->orderBy('parcel_no')
            ->pluck('parcel_no', 'id')
            ->all();
    }

    /**
     * Deeds of the chosen parcel, as id => deed number. Empty until a parcel
     * is chosen, which is also when the dropdown is offered.
     *
     * @return array<int, string>
     */
    private function deedOptions(): array
    {
        $parcelId = $this->form->parcel();

        if ($parcelId === null) {
            return [];
        }

        return Deed::query()
            ->where('parcel_id', $parcelId)
            ->orderBy('deed_no')
            ->pluck('deed_no', 'id')
            ->all();
    }

    /** The signed-in dashboard user, or null — OwnerScope speaks that type. */
    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
