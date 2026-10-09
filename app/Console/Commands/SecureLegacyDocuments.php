<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ParcelPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves documents that predate the upload feature off the public disk.
 *
 * Those rows carry a public URL in photo_url and no storage_disk, and their
 * files sat under /storage/documents — reachable by anyone who guessed the
 * name (a deed's file is named after its number). Each file is moved to the
 * private disk at the same relative path and its row re-pointed, so it is
 * served only through the audited, permission-checked routes. Safe to re-run.
 */
class SecureLegacyDocuments extends Command
{
    /** Everything under this folder of the public disk is a legal document. */
    private const PUBLIC_FOLDER = 'documents';

    protected $signature = 'documents:secure-legacy {--dry-run : Report what would move without changing anything}';

    protected $description = 'Move legacy public documents to the private disk and re-point their rows';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $public = Storage::disk(ParcelPhoto::LEGACY_DISK);
        $private = Storage::disk(ParcelPhoto::PRIVATE_DISK);
        $rows = 0;
        $missing = 0;

        // The review scope hides documents awaiting approval; they are moved too.
        $legacy = ParcelPhoto::withoutGlobalScopes()->where(fn ($q) => $q->whereNull('storage_disk')->orWhere('storage_disk', ''));

        foreach ($legacy->cursor() as $photo) {
            $path = $photo->storageLocation()['path'];

            if (! $public->exists($path) && ! $private->exists($path)) {
                $missing++;
                $this->warn("Row {$photo->id}: file not found, left as is.");

                continue;
            }

            if (! $dryRun) {
                $this->move($path);
                $photo->forceFill(['storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_url' => $path])->saveQuietly();
            }
            $rows++;
        }

        // Files no row points at are still deeds and sketches: they leave the public disk as well.
        $orphans = 0;
        foreach ($public->allFiles(self::PUBLIC_FOLDER) as $path) {
            if (basename($path) === '.htaccess') {
                continue;
            }
            if (! $dryRun) {
                $this->move($path);
            }
            $orphans++;
        }

        $verb = $dryRun ? 'would be' : 'were';
        $this->info("{$rows} rows {$verb} re-pointed, {$orphans} unreferenced files {$verb} moved, {$missing} rows have no file.");

        return self::SUCCESS;
    }

    /** Copy first, delete after: an interrupted run never loses a file. */
    private function move(string $path): void
    {
        $public = Storage::disk(ParcelPhoto::LEGACY_DISK);
        $private = Storage::disk(ParcelPhoto::PRIVATE_DISK);

        if (! $public->exists($path)) {
            return;
        }

        // A different file already at this private path is a leftover: the
        // public one is what the row has been serving, so it wins.
        if (! $private->exists($path) || $private->size($path) !== $public->size($path)) {
            $stream = $public->readStream($path);
            $private->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($private->exists($path) && $private->size($path) === $public->size($path)) {
            $public->delete($path);
        }
    }
}
