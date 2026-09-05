<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ImportWizard extends Component
{
    public ?string $batchUuid = null;

    public function mount(?string $batchUuid = null): void
    {
        $this->batchUuid = $batchUuid;
    }

    public function batch(): ?ImportBatch
    {
        return $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->first();
    }

    /**
     * Livewire actions are client-callable, so this authorizes server-side
     * rather than relying on the blade @can that hides the button.
     */
    public function confirm(): void
    {
        $this->authorize('imports.create');

        $batch = $this->batch();

        abort_if($batch === null, 404);
        abort_unless($batch->user_id === auth()->id(), 403);

        if ($batch->status !== ImportStatus::Previewed) {
            return;
        }

        $batch->dispatchCommit();
    }

    public function startOver(): void
    {
        $this->batchUuid = null;
    }

    public function render(): View
    {
        return view('livewire.imports.import-wizard', [
            'currentBatch' => $this->batch(),
            'recent' => ImportBatch::query()->with('user')->latest()->limit(10)->get(),
        ]);
    }
}
