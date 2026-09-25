<?php

declare(strict_types=1);

namespace App\Livewire\AuditLogs;

use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\AuditLog;
use App\Support\AuditActions;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogIndex extends Component
{
    use FiltersByCreatedAt;
    use WithPagination;

    public string $search = '';

    public string $actionFilter = 'all';

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
                    // Codes are English and people search in Arabic, so the
                    // term is also matched against each action's label.
                    $inner->whereLike('action', '%'.$this->search.'%')
                        ->orWhereIn('action', AuditActions::matching($this->search))
                        ->orWhereHas('user', fn ($u) => $u->whereLike('name', '%'.$this->search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->tap(fn ($q) => $this->applyCreatedAt($q))
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.audit-logs.audit-log-index', [
            'logs' => $this->logs(),
            'actionOptions' => AuditActions::recorded(),
        ]);
    }
}
