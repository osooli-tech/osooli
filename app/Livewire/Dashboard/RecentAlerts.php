<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\DeedStatus;
use App\Models\Deed;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class RecentAlerts extends Component
{
    /** @var Collection<int, Deed> */
    public Collection $alerts;

    public int $totalCount = 0;

    public function mount(): void
    {
        $this->totalCount = Deed::where('deed_status', DeedStatus::Old->value)->count();

        $this->alerts = Deed::with('parcel')
            ->where('deed_status', DeedStatus::Old->value)
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.recent-alerts');
    }
}
