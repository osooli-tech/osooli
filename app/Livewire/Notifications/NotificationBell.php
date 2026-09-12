<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\ModificationRequestStatus;
use App\Models\ModificationRequest;
use App\Models\PresentationRequest;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    /** Count as of the last render, used to detect new arrivals between polls. */
    public int $lastKnownCount = 0;

    public function mount(): void
    {
        $this->lastKnownCount = $this->count();
    }

    /** Called on every wire:poll tick from the layout. */
    public function poll(): void
    {
        $current = $this->count();

        if ($current > $this->lastKnownCount) {
            $this->dispatch('play-notification-sound');
        }

        $this->lastKnownCount = $current;
    }

    #[Computed]
    public function count(): int
    {
        return ($this->presentationRequests()?->count() ?? 0)
            + ($this->modificationRequests()?->count() ?? 0);
    }

    /**
     * The most recent unread items from both sources, newest first.
     *
     * @return list<array{type: string, title: string, subtitle: string, url: string, created_at: Carbon}>
     */
    #[Computed]
    public function items(): array
    {
        $presentationRequests = collect($this->presentationRequests()?->latest()->limit(5)->get())
            ->map(fn (PresentationRequest $r) => [
                'type' => 'presentation_request',
                'title' => $r->name,
                'subtitle' => $r->phone,
                'url' => route('presentation-requests.index'),
                'created_at' => $r->created_at,
            ]);

        $modificationRequests = collect($this->modificationRequests()?->with('parcel')->latest()->limit(5)->get())
            ->map(fn (ModificationRequest $r) => [
                'type' => 'modification_request',
                'title' => $r->parcel->parcel_no,
                'subtitle' => $r->fieldLabel(),
                // Opens this request's detail straight away, not just the list
                'url' => route('modification-requests.index', ['request' => $r->id]),
                'created_at' => $r->created_at,
            ]);

        return $presentationRequests
            ->concat($modificationRequests)
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Unread demo requests — or null when this user may not open the page a
     * notification links to, so the bell never leads anyone to a refusal.
     *
     * @return Builder<PresentationRequest>|null
     */
    private function presentationRequests(): ?Builder
    {
        if (! $this->user()?->can('presentation_requests.view')) {
            return null;
        }

        return PresentationRequest::query()->whereNull('read_at');
    }

    /**
     * Pending modification requests on parcels this user can see — or null
     * without permission to open the requests page.
     *
     * @return Builder<ModificationRequest>|null
     */
    private function modificationRequests(): ?Builder
    {
        $user = $this->user();

        if (! $user?->can('modification_requests.view')) {
            return null;
        }

        $parcelIds = OwnerScope::parcelIds($user);

        return ModificationRequest::query()
            ->where('status', ModificationRequestStatus::Pending)
            ->when($parcelIds !== null, fn (Builder $q) => $q->whereIn('parcel_id', $parcelIds));
    }

    private function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.notifications.notification-bell');
    }
}
