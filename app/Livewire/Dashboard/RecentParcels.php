<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Parcel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class RecentParcels extends Component
{
    /** @var Collection<int, Parcel> */
    public Collection $parcels;

    public function mount(): void
    {
        $this->parcels = Parcel::with('deeds')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.recent-parcels');
    }
}
