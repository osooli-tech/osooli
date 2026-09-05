<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportBatch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Reaps staged upload archives that ImportBatch::scopeStale() (Task 6) finds
 * past the retention window.
 *
 * Since Task 8, stored_path is written at batch *creation* time, before a
 * single byte has landed on disk, precisely so an abandoned upload (tab
 * closed mid-transfer) is not invisible to this command forever. That means
 * stale() legitimately returns batches in any status — Uploading, Failed,
 * and Completed alike — and this command treats them identically: it only
 * cares about "is there a staging directory for this uuid", never about
 * status.
 */
class PruneImportBatches extends Command
{
    protected $signature = 'app:prune-import-batches {--days= : Override the retention window}';

    protected $description = 'Delete staged import archives older than the retention window, keeping the batch rows as history';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('imports.retention_days'));
        $disk = Storage::disk('local');

        // The root every batch's staging directory must live under. Resolved
        // once via realpath() (not string-built) so the containment check
        // below cannot be fooled by a relative segment.
        $importsRoot = realpath($disk->path('imports'));

        $pruned = 0;

        foreach (ImportBatch::query()->stale($days)->get() as $batch) {
            $this->deleteStagingDirectory($disk, $importsRoot, $batch->uuid);

            // The row itself is kept as history (see the command
            // description) — only stored_path is cleared, both because the
            // file it named is gone and so scopeStale()'s
            // whereNotNull('stored_path') does not hand this row back to a
            // later run.
            $batch->forceFill(['stored_path' => null])->save();
            $pruned++;
        }

        $this->info("Staged import archives pruned: {$pruned}");

        return self::SUCCESS;
    }

    /**
     * Removes the on-disk staging directory for one batch, if it still
     * exists.
     *
     * Deliberately never looks at $batch->stored_path to decide what to
     * delete. That column is free-form text on the model — a future bug, a
     * bad backfill, or simply an empty string could leave it pointing
     * anywhere, and a command that recursively deletes directories must not
     * let a surprising value there redirect where it deletes. The uuid, by
     * contrast, is what ImportUploadController::uploadDirectory() already
     * uses to name every batch's directory ('imports/{uuid}'), is a
     * server-generated, non-nullable, unique column, and contains no path
     * separators — so rebuilding that same relative path here and verifying
     * with realpath() that it resolves inside $importsRoot is both the
     * correct location and a belt-and-braces guard against ever deleting
     * outside the staging area.
     *
     * A directory that does not exist (already removed by
     * ImportUploadController's own failure-path cleanup, or never created at
     * all for a batch that failed before its first chunk) is the normal,
     * expected case for many stale rows, not an error — it is simply
     * skipped.
     */
    private function deleteStagingDirectory(Filesystem $disk, string|false $importsRoot, string $uuid): void
    {
        if ($importsRoot === false || $uuid === '') {
            return;
        }

        $relative = 'imports/'.$uuid;
        $real = realpath($disk->path($relative));

        if ($real === false) {
            return;
        }

        if (! str_starts_with($real, $importsRoot.DIRECTORY_SEPARATOR)) {
            return;
        }

        $disk->deleteDirectory($relative);
    }
}
