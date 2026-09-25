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

    /**
     * The batch shown by render() — scoped to the current user.
     *
     * $batchUuid is a public Livewire property, so it is client-settable:
     * any authenticated user holding imports.create could otherwise point it
     * at someone else's batch and have it fully rendered here (preview
     * counts, warnings, result breakdown, error_message). Scoping the lookup
     * itself means a foreign uuid resolves to null and the component falls
     * back to its "no batch yet" state, exactly as if $batchUuid had never
     * been set — it never proves whether that uuid belongs to someone else.
     *
     * This intentionally does not use #[Locked]: the upload JS legitimately
     * sets this property client-side via $wire.set('batchUuid', uuid) once
     * create() returns, and #[Locked] would reject that.
     */
    public function batch(): ?ImportBatch
    {
        return $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->where('user_id', auth()->id())->first();
    }

    /**
     * Livewire actions are client-callable, so this authorizes server-side
     * rather than relying on the blade @can that hides the button.
     */
    public function confirm(): void
    {
        $this->authorize('imports.create');

        // Looked up unscoped (unlike batch() above) so that a batch which
        // exists but belongs to someone else is distinguished from one that
        // does not exist at all: 403 for the former, 404 for the latter.
        $batch = $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->first();

        abort_if($batch === null, 404);
        abort_unless($batch->user_id === auth()->id(), 403);

        if ($batch->status !== ImportStatus::Previewed) {
            return;
        }

        if ($batch->stored_path === null) {
            // PruneImportBatches reaps a staged batch's file after the
            // retention window regardless of status — including Previewed —
            // and nulls stored_path when it does. Without this check, a
            // stale confirm click would dispatch a commit that immediately
            // fails to find the file and reports CommitImportBatch's partial
            // -write notice ("Some records may already be committed") for a
            // write that never even started. Refusing here, with its own
            // terminal Failed status and a message that says what actually
            // happened, is markedly better than that false alarm — see I6 in
            // the final review.
            $batch->markFailed(__('imports.errors.staged_file_missing'));

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
