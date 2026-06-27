<?php

declare(strict_types=1);

namespace App\Livewire\AuditLogs;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $actionFilter = 'all';

    /** @var string[] */
    public array $actionOptions = [
        'login',
        'logout',
        'download',
        'export',
        'modification_request status changed',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<AuditLog> */
    private function logs(): LengthAwarePaginator
    {
        return AuditLog::with('user')
            ->when($this->actionFilter !== 'all', fn ($q) => $q->where('action', $this->actionFilter))
            ->when($this->search !== '', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('action', 'ilike', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', '%'.$this->search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.audit-logs.audit-log-index', [
            'logs' => $this->logs(),
        ]);
    }
}
