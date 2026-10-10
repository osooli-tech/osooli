<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ParcelPhoto;
use App\Support\DocumentVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Encrypts every document already on the private disk.
 *
 * New uploads are sealed as they land; this brings the files stored before
 * that up to the same state. Each file is checked to decrypt back to its
 * original bytes before it is replaced, and a file already sealed is skipped,
 * so the command can be stopped and run again at any point.
 */
class EncryptStoredDocuments extends Command
{
    protected $signature = 'documents:encrypt {--dry-run : Count the files that would be encrypted without changing any}';

    protected $description = 'Encrypt the documents stored on the private disk (AES-256-GCM)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(ParcelPhoto::PRIVATE_DISK);
        $sealed = 0;
        $already = 0;
        $failed = 0;

        foreach ($disk->allFiles() as $path) {
            try {
                if (DocumentVault::isSealed((string) $disk->get($path))) {
                    $already++;

                    continue;
                }

                if (! $dryRun) {
                    DocumentVault::sealOnDisk(ParcelPhoto::PRIVATE_DISK, $path);
                }
                $sealed++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$path}: {$e->getMessage()}");
            }
        }

        $verb = $dryRun ? 'would be' : 'were';
        $this->info("{$sealed} files {$verb} encrypted, {$already} already were, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
