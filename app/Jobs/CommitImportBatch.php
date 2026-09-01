<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\Import\ImporterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class CommitImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);

        // Only Previewed may become Committing, so a batch that was never
        // analysed and a batch already committed both fall out here, before
        // any work happens. This is the whole double-write guard.
        if ($batch === null || ! $batch->transitionTo(ImportStatus::Committing)) {
            return;
        }

        try {
            $result = app(ImporterFactory::class)->for($batch->kind)->commit((string) $batch->stored_path);

            $batch->transitionTo(ImportStatus::Completed, [
                'result' => $result->toArray(),
                'committed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $batch->markFailed($e->getMessage());
        }
    }
}
