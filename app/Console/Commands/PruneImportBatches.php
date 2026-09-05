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
 *
 * stale() only looks at created_at. On top of it, this command additionally
 * requires updated_at to also be outside the retention window, because
 * ImportUploadController::chunk() touches updated_at on every appended
 * chunk. Without that second condition, a batch old enough to be stale by
 * created_at but still actively receiving chunks right now would have its
 * staging directory deleted out from under the open file handle still
 * writing to it. That is self-healing — complete()'s size check catches the
 * mismatch and fails that one batch — but skipping it here is free and
 * avoids the failure entirely. This is deliberately a condition added here,
 * not a change to ImportBatch::scopeStale() itself, whose behaviour is
 * pinned by Task 6's tests.
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
        $alreadyMissing = 0;
        $failed = 0;

        // See the class docblock for why updated_at is filtered here rather
        // than in scopeStale().
        $notTouchedSince = now()->subDays($days);

        foreach (ImportBatch::query()->stale($days)->where('updated_at', '<', $notTouchedSince)->get() as $batch) {
            $result = $this->deleteStagingDirectory($disk, $importsRoot, $batch->uuid);

            if ($result === false) {
                // A directory was found but was not actually removed (a
                // permission error, a Windows file lock, or the containment
                // guard refusing an unexpected path) — or deleteDirectory()
                // simply reported failure. The local disk is configured with
                // 'throw' => false and 'report' => false (config/
                // filesystems.php), so this return value is the *only*
                // signal such a failure produces: no exception, no log
                // entry. stored_path is deliberately left set so
                // scopeStale()'s whereNotNull('stored_path') hands this row
                // back to the very next run instead of losing track of it
                // forever. One failed batch must not stop the rest of the
                // run, so processing continues.
                $failed++;

                continue;
            }

            $result === null ? $alreadyMissing++ : $pruned++;

            // The row itself is kept as history (see the command
            // description) — only stored_path is cleared, and only now that
            // the directory is genuinely gone (deleted just above, or
            // already gone before this run), so scopeStale()'s
            // whereNotNull('stored_path') does not hand this row back to a
            // later run.
            $batch->forceFill(['stored_path' => null])->save();
        }

        $this->info("Staged import archives pruned: {$pruned} (already missing: {$alreadyMissing})");

        if ($failed > 0) {
            $this->warn("Staged import archives that failed to delete: {$failed} — left in place, stored_path kept, for the next run to retry.");
        }

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
     *
     * @return bool|null true if a directory was found and deleteDirectory()
     *                   reported it was actually removed; null if there was
     *                   nothing to remove (no staging directory ever
     *                   existed, or it was already gone); false if a
     *                   directory was found but was not removed — either
     *                   deleteDirectory() itself reported failure, or the
     *                   containment guard refused to touch a path that did
     *                   not resolve inside $importsRoot. Callers must treat
     *                   null and false differently: null means the caller's
     *                   stored_path is safe to clear, false means it is not.
     */
    private function deleteStagingDirectory(Filesystem $disk, string|false $importsRoot, string $uuid): ?bool
    {
        if ($importsRoot === false || $uuid === '') {
            return null;
        }

        $relative = 'imports/'.$uuid;
        $real = realpath($disk->path($relative));

        if ($real === false) {
            return null;
        }

        if (! str_starts_with($real, $importsRoot.DIRECTORY_SEPARATOR)) {
            return false;
        }

        return $disk->deleteDirectory($relative);
    }
}
