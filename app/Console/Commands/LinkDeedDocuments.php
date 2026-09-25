<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\DocumentImporter;
use Illuminate\Console\Command;
use ZipArchive;

class LinkDeedDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:link-deed-documents';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link deed PDF scans (named by deed number) in storage/app/public/documents/deeds to their parcels as "صك" documents';

    /**
     * Zips storage/app/public/documents/deeds and hands it to DocumentImporter,
     * so this CLI path shares the same rule detection and multi-parcel deed
     * fan-out as the dashboard import instead of its own ->first()-based match.
     */
    public function handle(DocumentImporter $importer): int
    {
        $dir = storage_path('app/public/documents/deeds');

        if (! is_dir($dir)) {
            $this->error("Directory not found: {$dir}");

            return self::FAILURE;
        }

        // glob('*.pdf') is case-sensitive, which would silently drop a
        // "*.PDF" scan (real scans routinely carry an upper-case extension).
        // DocumentImporter::inspect() already matches case-insensitively via
        // strtolower($file->getExtension()) — this mirrors that here, since
        // it is the zip-building step, not the importer, that reads the raw
        // directory listing.
        $pdfs = array_filter(
            glob($dir.'/*') ?: [],
            static fn (string $path): bool => is_file($path) && preg_match('/\.pdf$/i', $path) === 1
        );

        if ($pdfs === []) {
            $this->info("No PDF files found in {$dir}; nothing to link.");

            return self::SUCCESS;
        }

        $zipPath = storage_path('app/private/link-deeds-'.uniqid().'.zip');
        $zip = new ZipArchive;

        // ZipArchive::close() is documented to fail (and, on some libzip
        // versions, simply never write the file) for a zero-entry archive —
        // guarded against above by the empty-$pdfs check — but open() itself
        // can also fail (a permission error, a full disk under storage/app/
        // private). Ignoring that return value used to mean $zipPath might
        // not exist at all by the time it reached commit() below, which then
        // threw an uncaught ArchiveException instead of a clean command
        // failure.
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Could not create a temporary archive at: {$zipPath}");

            return self::FAILURE;
        }

        foreach ($pdfs as $pdf) {
            $zip->addFile($pdf, basename($pdf));
        }

        if (! $zip->close()) {
            $this->error("Could not write the temporary archive at: {$zipPath}");

            return self::FAILURE;
        }

        try {
            $result = $importer->commit($zipPath);
        } finally {
            @unlink($zipPath);
        }

        $this->info("Deed documents linked: {$result->created}");

        if ($result->skipped > 0) {
            $this->warn('PDFs with no matching deed ('.$result->skipped.'): '
                .implode(', ', array_slice($result->details['unmatched_files'] ?? [], 0, 15)));
        }

        return self::SUCCESS;
    }
}
