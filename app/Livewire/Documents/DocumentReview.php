<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Models\ParcelPhoto;
use App\Models\User;
use App\Support\Concerns\WritesSafely;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The queue an uploaded document sits in until someone decides on it.
 *
 * Both decisions are writes like any other, so both go through
 * writeSafely(): a transaction, and an audit entry naming who approved or
 * rejected which document. A rejection carries a written reason, because
 * "rejected" on its own tells the uploader nothing they can act on.
 */
class DocumentReview extends Component
{
    use WithPagination;
    use WritesSafely;

    /** @var list<string> Statuses offered in the filter, in workflow order. */
    public const STATUSES = [
        ParcelPhoto::STATUS_PENDING,
        ParcelPhoto::STATUS_APPROVED,
        ParcelPhoto::STATUS_REJECTED,
    ];

    private const REASON_MIN = 5;

    private const REASON_MAX = 1000;

    public string $filterStatus = ParcelPhoto::STATUS_PENDING;

    public string $search = '';

    /** Document the rejection dialog is open for. */
    #[Locked]
    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        $this->authorise();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function approve(int $documentId): void
    {
        $document = $this->findForReview($documentId);

        if (! $document->isPending()) {
            $this->alreadyDecided();

            return;
        }

        $this->writeSafely(
            'document.approve',
            'document',
            $document->id,
            function () use ($document): void {
                $document->update([
                    'status' => ParcelPhoto::STATUS_APPROVED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    // A document approved after an earlier rejection must not
                    // keep showing the old reason next to it.
                    'rejection_reason' => null,
                ]);
            }
        );

        $this->dispatch('toast', type: 'success', message: __('documents.approved'));
    }

    public function confirmReject(int $documentId): void
    {
        $document = $this->findForReview($documentId);

        if (! $document->isPending()) {
            $this->alreadyDecided();

            return;
        }

        $this->rejectingId = $document->id;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectionReason = '';
        $this->resetValidation();
    }

    public function reject(): void
    {
        if ($this->rejectingId === null) {
            return;
        }

        $this->validate(
            ['rejectionReason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX]],
            [],
            ['rejectionReason' => __('documents.rejection_reason')]
        );

        $document = $this->findForReview($this->rejectingId);

        if (! $document->isPending()) {
            $this->cancelReject();
            $this->alreadyDecided();

            return;
        }

        $reason = trim($this->rejectionReason);

        $this->writeSafely(
            'document.reject',
            'document',
            $document->id,
            function () use ($document, $reason): void {
                $document->update([
                    'status' => ParcelPhoto::STATUS_REJECTED,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'rejection_reason' => $reason,
                ]);
            }
        );

        $this->cancelReject();
        $this->dispatch('toast', type: 'error', message: __('documents.rejected'));
    }

    public function render(): View
    {
        $documents = $this->documents();

        return view('livewire.documents.document-review', [
            'documents' => $documents,
            'reviewers' => $this->peopleNames($documents),
            'statuses' => self::STATUSES,
        ]);
    }

    /** @return LengthAwarePaginator<ParcelPhoto> */
    private function documents(): LengthAwarePaginator
    {
        $allowed = OwnerScope::parcelIds($this->currentUser());

        return ParcelPhoto::query()
            ->forReview($this->filterStatus !== '' ? $this->filterStatus : null)
            ->with(['parcel.plan', 'deed'])
            ->when($allowed !== null, fn (Builder $query) => $query->whereIn('parcel_id', $allowed ?? []))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('original_name', 'ilike', $term)
                        ->orWhereHas('parcel', fn (Builder $p) => $p->where('parcel_no', 'ilike', $term));
                });
            })
            ->latest()
            ->paginate(20);
    }

    /**
     * Names for the uploader/reviewer columns, keyed by user id.
     *
     * Looked up in one query for the whole page instead of through relations
     * on the model, which is meant to gain no new ones for this feature.
     *
     * @param  LengthAwarePaginator<ParcelPhoto>  $documents
     * @return array<int, string>
     */
    private function peopleNames(LengthAwarePaginator $documents): array
    {
        $ids = [];

        foreach ($documents->items() as $document) {
            foreach ([$document->uploaded_by, $document->reviewed_by] as $id) {
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', array_keys($ids))
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Load a document of any status, refusing one outside this user's scope.
     *
     * forReview() is what lifts the model's global scope; without it a pending
     * document would not even be found here.
     */
    private function findForReview(int $documentId): ParcelPhoto
    {
        $this->authorise();

        $document = ParcelPhoto::query()->forReview()->whereKey($documentId)->firstOrFail();

        abort_unless(OwnerScope::canSeeParcel($this->currentUser(), $document->parcel_id), 403);

        return $document;
    }

    /**
     * The call sites hide the buttons behind a permission check, but that only
     * removes the markup — a request aimed straight at this component still
     * arrives, so the check has to live here too.
     */
    private function authorise(): void
    {
        abort_unless(auth()->user()?->can('documents.review'), 403);
    }

    private function alreadyDecided(): void
    {
        $this->dispatch('toast', type: 'error', message: __('documents.already_reviewed'));
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
