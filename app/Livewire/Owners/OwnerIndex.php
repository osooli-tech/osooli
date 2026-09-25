<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\Owner;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OwnerIndex extends Component
{
    use FiltersByCreatedAt;
    use WithPagination;

    public string $search = '';

    /** @var array<int, bool> */
    public array $expanded = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->expanded = [];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'createdFrom', 'createdTo']);
        $this->resetPage();
        $this->expanded = [];
    }

    /**
     * Re-renders this list so an owner added or edited in the modal — or a
     * share changed in a deed — shows up in the rows immediately.
     */
    #[On('owner-updated')]
    #[On('owner-saved')]
    #[On('ownership-updated')]
    public function refreshOwners(): void {}

    public function toggleExpand(int $ownerId): void
    {
        $this->expanded[$ownerId] = ! ($this->expanded[$ownerId] ?? false);
    }

    /** @return LengthAwarePaginator<Owner> */
    private function owners(): LengthAwarePaginator
    {
        /** @var User|null $user */
        $user = Auth::user();
        $ownerIds = OwnerScope::ownerIds($user);

        return Owner::query()
            ->withCount('currentDeeds as parcel_count')
            ->withCount('deeds')
            ->when($ownerIds !== null, fn ($q) => $q->whereIn('id', $ownerIds))
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->whereLike('name', $term)
                        ->orWhereLike('national_id', $term)
                        ->orWhereLike('phone', $term);
                });
            })
            ->orderBy('name')
            ->tap(fn ($q) => $this->applyCreatedAt($q))
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.owners.owner-index', [
            'owners' => $this->owners(),
        ]);
    }
}
