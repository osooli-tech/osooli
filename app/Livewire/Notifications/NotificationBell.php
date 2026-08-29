<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\ModificationRequestStatus;
use App\Models\ModificationRequest;
use App\Models\PresentationRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    /** Count as of the last render, used to detect new arrivals between polls. */
    public int $lastKnownCount = 0;

    public function mount(): void
    {
        $this->lastKnownCount = $this->count;
    }

    /** Called on every wire:poll tick from the layout. */
    public function poll(): void
    {
        $current = $this->count;

        if ($current > $this->lastKnownCount) {
            $this->dispatch('play-notification-sound');
        }

        $this->lastKnownCount = $current;
    }

    #[Computed]
    public function count(): int
    {
        return PresentationRequest::whereNull('read_at')->count()
            + ModificationRequest::where('status', ModificationRequestStatus::Pending)->count();
    }

    /**
     * The most recent unread items from both sources, newest first.
     *
     * @return list<array{type: string, title: string, subtitle: string, url: string, created_at: \Illuminate\Support\Carbon}>
     */
    #[Computed]
    public function items(): array
    {
        $presentationRequests = PresentationRequest::whereNull('read_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PresentationRequest $r) => [
                'type' => 'presentation_request',
                'title' => $r->name,
                'subtitle' => $r->phone,
                'url' => route('presentation-requests.index'),
                'created_at' => $r->created_at,
            ]);

        $modificationRequests = ModificationRequest::where('status', ModificationRequestStatus::Pending)
            ->with('parcel')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ModificationRequest $r) => [
                'type' => 'modification_request',
                'title' => $r->parcel?->parcel_no ?? '—',
                'subtitle' => $r->fieldLabel(),
                'url' => route('modification-requests.index'),
                'created_at' => $r->created_at,
            ]);

        return $presentationRequests
            ->concat($modificationRequests)
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.notifications.notification-bell');
    }
}
