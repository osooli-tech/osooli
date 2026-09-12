<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecentAlerts extends Component
{
    /** @var Collection<int, Deed> */
    public Collection $alerts;

    public int $totalCount = 0;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        // Each alert links to its parcel's page, which refuses a parcel outside
        // a restricted user's scope — so neither the list nor the count may
        // include one.
        $parcelIds = OwnerScope::parcelIds($user);

        $oldDeeds = Deed::query()
            ->where('deed_status', DeedStatus::Old->value)
            ->when($parcelIds !== null, fn ($q) => $q->whereIn('parcel_id', $parcelIds));

        $this->totalCount = (clone $oldDeeds)->count();

        $this->alerts = (clone $oldDeeds)
            ->with('parcel')
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.recent-alerts');
    }
}
