<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoType;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LinkParcelSitePhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:link-parcel-site-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link site photos (named "{plan}-{suffix}.jpg") in storage/app/public/documents/site-photos to their parcels by geo_id';

    public function handle(): int
    {
        $dir = 'documents/site-photos';
        $disk = Storage::disk('public');

        if (! $disk->exists($dir)) {
            $this->error("Directory not found: storage/app/public/{$dir}");

            return self::FAILURE;
        }

        $linked = 0;
        $noMatch = [];

        foreach ($disk->files($dir) as $path) {
            if (! preg_match('/\.jpe?g$/i', $path)) {
                continue;
            }

            $stem = trim(pathinfo($path, PATHINFO_FILENAME));
            $dashPos = strpos($stem, '-');

            if ($dashPos === false) {
                $noMatch[] = $stem;

                continue;
            }

            $partA = substr($stem, 0, $dashPos);
            $partB = substr($stem, $dashPos + 1);

            // geo_id ordering is inconsistent in this source — some parcels
            // are "{plan}-{suffix}" (e.g. "1165-3ب"), others "{suffix}-{plan}"
            // (e.g. "2أ-1165") — so a file is matched against both orderings
            // rather than assuming one.
            $parcel = Parcel::where('geo_id', "{$partA}-{$partB}")
                ->orWhere('geo_id', "{$partB}-{$partA}")
                ->first();

            if ($parcel === null) {
                $noMatch[] = $stem;

                continue;
            }

            ParcelPhoto::updateOrCreate(
                ['parcel_id' => $parcel->id, 'photo_type' => PhotoType::Aerial->value],
                ['photo_url' => '/storage/'.$path]
            );
            $linked++;
        }

        $this->info("Site photos linked: {$linked}");

        if ($noMatch !== []) {
            $this->warn('Files with no matching parcel: '.implode(', ', $noMatch));
        }

        return self::SUCCESS;
    }
}
