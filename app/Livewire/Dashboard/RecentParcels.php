<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Parcel;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecentParcels extends Component
{
    /** @var Collection<int, Parcel> */
    public Collection $parcels;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        // Each row links to the parcel page, which refuses a parcel outside a
        // restricted user's scope — so the list must not offer one either.
        $parcelIds = OwnerScope::parcelIds($user);

        $this->parcels = Parcel::with('deeds')
            ->when($parcelIds !== null, fn ($q) => $q->whereIn('id', $parcelIds))
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.recent-parcels');
    }
}
