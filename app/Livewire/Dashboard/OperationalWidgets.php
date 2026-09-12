<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\ModificationRequest;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OperationalWidgets extends Component
{
    public int $pendingModRequests = 0;

    /** The active-user count is system-wide, so it is only shown to someone who manages users. */
    public bool $showActiveUsers = false;

    public int $activeUsers = 0;

    public ?string $lastSyncHuman = null;

    public ?string $lastSyncStatus = null;

    public int $lastSyncImported = 0;

    public int $lastSyncUpdated = 0;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $parcelIds = OwnerScope::parcelIds($user);

        $this->pendingModRequests = ModificationRequest::where('status', 'pending')
            ->when($parcelIds !== null, fn ($q) => $q->whereIn('parcel_id', $parcelIds))
            ->count();

        $this->showActiveUsers = (bool) $user?->can('users.view');

        if ($this->showActiveUsers) {
            $this->activeUsers = User::where('is_active', true)->count();
        }

        $lastSync = SyncLog::latest('sync_started_at')->first();

        if ($lastSync !== null) {
            $this->lastSyncHuman = $lastSync->sync_started_at->diffForHumans();
            $this->lastSyncStatus = $lastSync->status;
            $this->lastSyncImported = $lastSync->records_imported;
            $this->lastSyncUpdated = $lastSync->records_updated;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.operational-widgets');
    }
}
