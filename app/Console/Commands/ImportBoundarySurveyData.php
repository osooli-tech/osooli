<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoType;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportBoundarySurveyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-boundary-survey-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill measured_area and link boundary survey PDFs for plan 623 (Saif Engineering Consultation cards)';

    /**
     * Measured area ("حسب الطبيعة") per parcel, extracted from the plan-623
     * boundary survey cards issued by Saif Engineering Consultation.
     *
     * @var array<int|string, float>
     */
    private const AREAS = [
        '131' => 33534.51, '133' => 25008.55, '135' => 26842.81, '138' => 25722.50,
        '164' => 25243.36, '165' => 25105.45, '166' => 26085.83, '167' => 25251.60,
        '218' => 25023.60, '219' => 25180.61, '220' => 25286.47, '221' => 25116.30,
        '243' => 25316.20, '244' => 25006.80, '29' => 31340.07, '30' => 25241.51,
        '308' => 25372.65, '309' => 25836.35, '31' => 25012.20, '310' => 25636.61,
        '316' => 25729.17, '34' => 27139.70, '37' => 25996.30, '377' => 25976.07,
        '378' => 25960.86, '383' => 25022.84, '384' => 25922.84, '96' => 25000.00,
        '97' => 25000.00, '98' => 25000.00, '99' => 25000.00,
    ];

    public function handle(): int
    {
        $plan = Plan::where('plan_no', '623')->first();

        if ($plan === null) {
            $this->error('Plan 623 not found — nothing to import.');

            return self::FAILURE;
        }

        $areaCount = 0;
        $linkedCount = 0;
        $missingFiles = [];

        foreach (self::AREAS as $parcelNo => $area) {
            $parcel = Parcel::where('plan_id', $plan->id)->where('parcel_no', $parcelNo)->first();

            if ($parcel === null) {
                $this->warn("Parcel {$parcelNo} not found under plan 623 — skipped.");

                continue;
            }

            if ($parcel->boundary !== null) {
                $parcel->boundary->update(['measured_area' => $area]);
                $areaCount++;
            }

            $relativePath = "documents/boundary-surveys/{$parcelNo}.pdf";

            if (! Storage::disk('public')->exists($relativePath)) {
                $missingFiles[] = $parcelNo;

                continue;
            }

            ParcelPhoto::updateOrCreate(
                ['parcel_id' => $parcel->id, 'photo_type' => PhotoType::BoundarySurvey->value],
                ['photo_url' => '/storage/'.$relativePath]
            );
            $linkedCount++;
        }

        $this->info("Measured area updated: {$areaCount}");
        $this->info("Boundary survey documents linked: {$linkedCount}");

        if ($missingFiles !== []) {
            $this->warn('Missing PDF files (upload to storage/app/public/documents/boundary-surveys/): '.implode(', ', $missingFiles));
        }

        return self::SUCCESS;
    }
}
