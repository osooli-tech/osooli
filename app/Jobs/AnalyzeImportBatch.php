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
}
