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

final class AnalyzeImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);

        // Only Uploaded may become Analyzing, so a batch that is already being
        // analysed (or beyond) by another worker falls out here, before any
        // work happens. A false return means someone else has this batch —
        // stop silently, do not retry, do not throw.
        if ($batch === null || ! $batch->transitionTo(ImportStatus::Analyzing)) {
            return;
        }

        try {
            $preview = app(ImporterFactory::class)->for($batch->kind)->analyze((string) $batch->stored_path);

            $batch->transitionTo(ImportStatus::Previewed, [
                'preview' => $preview->toArray(),
                'analyzed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $batch->markFailed($e->getMessage());
        }
    }

    /**
     * Called by the queue worker once this job is deemed to have failed for
     * good — including the cases the try/catch in handle() never sees, such
     * as the worker process being OOM-killed or hitting the queue-level
     * timeout outside that block. Without this, a batch killed mid-Analyzing
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
            'The analyze job did not finish running: '.$e->getMessage()
        );
    }
}
