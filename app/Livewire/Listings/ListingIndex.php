<?php

declare(strict_types=1);

namespace App\Livewire\Listings;

use App\Enums\ListingStatus;
use App\Models\Owner;
use App\Models\ParcelListing;
use App\Models\User;
use App\Services\Listings\ParcelListingService;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Staff review of owners' sale listings: approve, reject, take down, and hide one from chosen owners. */
class ListingIndex extends Component
{
    use WithPagination;

    private const OWNER_SEARCH_LIMIT = 8;

    public string $statusFilter = ListingStatus::Pending->value;

    public string $search = '';

    #[Url(as: 'listing')]
    public ?int $viewingId = null;

    public bool $showModal = false;

    public string $rejectionReason = '';

    public string $ownerSearch = '';

    /** Every request, not only the first: Livewire calls reach the component without the route's middleware. */
    public function boot(): void
    {
        abort_unless(Auth::user()?->can('listings.view') ?? false, 403);
    }

    public function mount(): void
    {
        $this->showModal = $this->viewingId !== null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openListing(int $id): void
    {
        $this->viewingId = $id;
        $this->rejectionReason = '';
        $this->ownerSearch = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('viewingId', 'showModal', 'rejectionReason', 'ownerSearch');
    }

    public function approve(ParcelListingService $listings): void
    {
        $this->authorize('listings.manage');
        $listings->approve($this->current(), $this->user());
        $this->done('listings.approved_msg');
    }

    public function reject(ParcelListingService $listings): void
    {
        $this->authorize('listings.manage');
        $this->validate(['rejectionReason' => ['required', 'string', 'max:500']], [], ['rejectionReason' => __('listings.reject_reason')]);
        $listings->reject($this->current(), $this->user(), $this->rejectionReason);
        $this->done('listings.rejected_msg');
    }

    public function takeDown(ParcelListingService $listings): void
    {
        $this->authorize('listings.manage');
        $listings->withdraw($this->current(), user: $this->user());
        $this->done('listings.taken_down_msg');
    }

    public function block(int $ownerId, ParcelListingService $listings): void
    {
        $this->authorize('listings.manage');
        $listings->block($this->current(), Owner::findOrFail($ownerId), $this->user());
        $this->ownerSearch = '';
        $this->dispatch('toast', type: 'success', message: __('listings.blocked_msg'));
    }

    public function unblock(int $ownerId, ParcelListingService $listings): void
    {
        $this->authorize('listings.manage');
        $listings->unblock($this->current(), Owner::findOrFail($ownerId), $this->user());
        $this->dispatch('toast', type: 'success', message: __('listings.unblocked_msg'));
    }

    #[Computed]
    public function viewing(): ?ParcelListing
    {
        return $this->viewingId === null ? null : $this->visible()
            ->with(['parcel.heldDeed.owners:id,name', 'parcel.plan.district', 'owner:id,name,phone', 'blockedOwners:id,name,phone', 'reviewer:id,name'])
            ->find($this->viewingId);
    }

    /** @return Collection<int, Owner> owners matching the search, not already hidden from and not the seller */
    #[Computed]
    public function ownerMatches(): Collection
    {
        $term = trim($this->ownerSearch);
        $listing = $this->viewing();

        if (mb_strlen($term) < 2 || $listing === null) {
            return new Collection;
        }

        return Owner::query()
            ->where(fn (Builder $q) => $q->whereLike('name', '%'.$term.'%')->orWhereLike('phone', '%'.$term.'%'))
            ->whereKeyNot([$listing->owner_id, ...$listing->blockedOwners->modelKeys()])
            ->orderBy('name')
            ->limit(self::OWNER_SEARCH_LIMIT)
            ->get(['id', 'name', 'phone']);
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        $byStatus = $this->visible()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return ['all' => (int) $byStatus->sum()] + collect(ListingStatus::cases())
            ->mapWithKeys(fn (ListingStatus $s): array => [$s->value => (int) ($byStatus[$s->value] ?? 0)])
            ->all();
    }

    public function render(): View
    {
        $listings = $this->visible()
            ->with(['parcel:id,parcel_no', 'owner:id,name'])
            ->when($this->statusFilter !== 'all', fn (Builder $q) => $q->where('status', ListingStatus::from($this->statusFilter)))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereHas('parcel', fn (Builder $p) => $p->whereLike('parcel_no', '%'.$this->search.'%'))
                ->orWhereHas('owner', fn (Builder $o) => $o->whereLike('name', '%'.$this->search.'%'))))
            ->latest('id')
            ->paginate(15);

        return view('livewire.listings.listing-index', ['listings' => $listings, 'statuses' => ListingStatus::cases()]);
    }

    private function current(): ParcelListing
    {
        return $this->visible()->findOrFail($this->viewingId);
    }

    private function done(string $message): void
    {
        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: __($message));
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Listings on parcels this user can see — a user restricted to specific
     * owners must not read or act on anyone else's.
     *
     * @return Builder<ParcelListing>
     */
    private function visible(): Builder
    {
        $parcelIds = OwnerScope::parcelIds($this->user());

        return ParcelListing::query()->when($parcelIds !== null, fn (Builder $q) => $q->whereIn('parcel_id', $parcelIds));
    }
}
