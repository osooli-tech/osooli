<?php

declare(strict_types=1);

namespace App\Livewire\PresentationRequests;

use App\Models\PresentationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class RequestIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        // Opening the list is how an admin acknowledges what's new, so
        // everything currently unread clears the moment they land here.
        PresentationRequest::whereNull('read_at')->update(['read_at' => now()]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator $requests */
        $requests = PresentationRequest::query()
            ->when(
                $this->search !== '',
                fn ($q) => $q->where(function ($inner) {
                    $inner->where('name', 'ilike', '%'.$this->search.'%')
                        ->orWhere('phone', 'ilike', '%'.$this->search.'%');
                })
            )
            ->latest()
            ->paginate(15);

        return view('livewire.presentation-requests.request-index', [
            'requests' => $requests,
        ]);
    }
}
