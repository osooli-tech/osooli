<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoType;
use App\Models\Deed;
use App\Models\ParcelPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

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

    public function handle(): int
    {
        $dir = 'documents/deeds';
        $disk = Storage::disk('public');

        if (! $disk->exists($dir)) {
            $this->error("Directory not found: storage/app/public/{$dir}");

            return self::FAILURE;
        }

        $files = $disk->files($dir);
        $linked = 0;
        $noMatch = [];

        foreach ($files as $path) {
            if (! preg_match('/\.pdf$/i', $path)) {
                continue;
            }

            // Filename (without extension) is the deed number.
            $deedNo = trim(pathinfo($path, PATHINFO_FILENAME));

            $deed = Deed::where('deed_no', $deedNo)->first();

            if ($deed === null) {
                $noMatch[] = $deedNo;

                continue;
            }

            ParcelPhoto::updateOrCreate(
                ['parcel_id' => $deed->parcel_id, 'photo_type' => PhotoType::Deed->value],
                ['photo_url' => '/storage/'.$path]
            );
            $linked++;
        }

        $this->info("Deed documents linked: {$linked}");

        if ($noMatch !== []) {
            $this->warn('PDFs with no matching deed ('.count($noMatch).'): '.implode(', ', array_slice($noMatch, 0, 15)).(count($noMatch) > 15 ? ' …' : ''));
        }

        return self::SUCCESS;
    }
}
