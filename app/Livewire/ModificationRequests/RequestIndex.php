<?php

declare(strict_types=1);

namespace App\Livewire\ModificationRequests;

use App\Enums\ModificationRequestStatus;
use App\Models\AuditLog;
use App\Models\ModificationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RequestIndex extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';

    public string $search = '';

    public bool $showModal = false;

    public ?int $viewingId = null;

    public string $managerNote = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openRequest(int $id): void
    {
        $this->viewingId = $id;
        $this->managerNote = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->viewingId = null;
        $this->managerNote = '';
    }

    public function changeStatus(string $newStatus): void
    {
        $this->authorize('modification_requests.manage');

        if ($this->viewingId === null) {
            return;
        }

        $request = ModificationRequest::findOrFail($this->viewingId);
        $transition = ModificationRequestStatus::from($newStatus);

        // Guard: only allow valid transitions
        if (! in_array($transition, $request->status->allowedTransitions(), true)) {
            $this->dispatch('swal:toast', type: 'error', message: __('modification_requests.invalid_transition'));

            return;
        }

        $request->update([
            'status' => $transition,
            'notes' => $this->managerNote ?: $request->notes,
            'resolved_at' => $transition->isResolved() ? now() : $request->resolved_at,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'status_change',
            'target_type' => 'modification_request',
            'target_id' => $request->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->closeModal();
        $this->dispatch('swal:toast', type: 'success', message: __('modification_requests.status_updated'));
    }

    #[Computed]
    public function viewing(): ?ModificationRequest
    {
        if ($this->viewingId === null) {
            return null;
        }

        return ModificationRequest::with(['parcel', 'owner'])->find($this->viewingId);
    }

    #[Computed]
    public function counts(): array
    {
        $base = ModificationRequest::query();

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', ModificationRequestStatus::Pending)->count(),
            'sent_to_arcgis' => (clone $base)->where('status', ModificationRequestStatus::SentToArcgis)->count(),
            'applied' => (clone $base)->where('status', ModificationRequestStatus::Applied)->count(),
            'rejected' => (clone $base)->where('status', ModificationRequestStatus::Rejected)->count(),
        ];
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator $requests */
        $requests = ModificationRequest::with(['parcel', 'owner'])
            ->when(
                $this->statusFilter !== 'all',
                fn ($q) => $q->where('status', ModificationRequestStatus::from($this->statusFilter))
            )
            ->when(
                $this->search !== '',
                fn ($q) => $q->where(function ($inner) {
                    $inner->whereHas('parcel', fn ($p) => $p->where('parcel_no', 'ilike', '%'.$this->search.'%'))
                        ->orWhere('field_name', 'ilike', '%'.$this->search.'%');
                })
            )
            ->latest()
            ->paginate(15);

        return view('livewire.modification-requests.request-index', [
            'requests' => $requests,
            'statuses' => ModificationRequestStatus::cases(),
        ]);
    }
}
