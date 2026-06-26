<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\ModificationRequest;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OperationalWidgets extends Component
{
    public int $pendingModRequests = 0;

    public int $activeUsers = 0;

    public ?string $lastSyncHuman = null;

    public ?string $lastSyncStatus = null;

    public int $lastSyncImported = 0;

    public int $lastSyncUpdated = 0;

    public function mount(): void
    {
        $this->pendingModRequests = ModificationRequest::where('status', 'pending')->count();
        $this->activeUsers = User::where('is_active', true)->count();

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
