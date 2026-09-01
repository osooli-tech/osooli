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

        $zipPath = storage_path('app/private/link-deeds-'.uniqid().'.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach (glob($dir.'/*.pdf') ?: [] as $pdf) {
            $zip->addFile($pdf, basename($pdf));
        }

        $zip->close();

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
