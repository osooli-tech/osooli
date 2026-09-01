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

    /**
     * Commit is not one transaction: ParcelGeoJsonImporter commits per
     * feature-group and DocumentImporter's write loop has no per-item
     * transaction, so an exception partway through can leave real rows
     * behind even though the batch ends up Failed. We deliberately do not
     * try to reconstruct how much landed — a count we cannot compute
     * honestly from an escaped exception is worse than none — so every
     * failure message says plainly that partial data may already be
     * committed, and that re-running is safe because every write in this
     * pipeline is an idempotent upsert.
     */
    private const PARTIAL_WRITE_NOTICE = ' Some records may already be committed; re-running this import is safe, because every write here is an idempotent upsert.';

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
            $batch->markFailed($e->getMessage().self::PARTIAL_WRITE_NOTICE);
        }
    }

    /**
     * Called by the queue worker once this job is deemed to have failed for
     * good — including the cases the try/catch in handle() never sees, such
     * as the worker process being OOM-killed or hitting the queue-level
     * timeout outside that block. Without this, a batch killed mid-Committing
     * would be stuck there forever: a redelivered attempt finds the batch
     * already mid-transition and the transitionTo() guard silently returns,
     * which Laravel records as a successful completion — nothing in `jobs`,
     * nothing in `failed_jobs`, no trace. markFailed() already refuses to
     * touch a batch that has already reached a terminal state, so this
     * cannot clobber a batch that in fact finished successfully in the
     * meantime.
     */
    public function failed(Throwable $e): void
    {
        ImportBatch::find($this->batchId)?->markFailed(
            'The commit job did not finish running: '.$e->getMessage().self::PARTIAL_WRITE_NOTICE
        );
    }
}
