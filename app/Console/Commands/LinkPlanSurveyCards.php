<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoType;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LinkPlanSurveyCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:link-plan-survey-cards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link boundary survey card scans (named by plan number) in storage/app/public/documents/plan-surveys to every parcel under that plan';

    public function handle(): int
    {
        $dir = 'documents/plan-surveys';
        $disk = Storage::disk('public');

        if (! $disk->exists($dir)) {
            $this->error("Directory not found: storage/app/public/{$dir}");

            return self::FAILURE;
        }

        $linked = 0;
        $noMatch = [];

        foreach ($disk->files($dir) as $path) {
            if (! preg_match('/\.tif$/i', $path)) {
                continue;
            }

            // Filename (without extension) is the plan number — one shared
            // survey card document covers the whole plan, not one parcel.
            $planNo = trim(pathinfo($path, PATHINFO_FILENAME));

            $plan = Plan::where('plan_no', $planNo)->first();

            if ($plan === null) {
                $noMatch[] = $planNo;

                continue;
            }

            $parcels = Parcel::where('plan_id', $plan->id)->get();

            foreach ($parcels as $parcel) {
                ParcelPhoto::updateOrCreate(
                    ['parcel_id' => $parcel->id, 'photo_type' => PhotoType::BoundarySurvey->value],
                    ['photo_url' => '/storage/'.$path]
                );
                $linked++;
            }
        }

        $this->info("Boundary survey cards linked: {$linked}");

        if ($noMatch !== []) {
            $this->warn('Plan numbers with no matching plan: '.implode(', ', $noMatch));
        }

        return self::SUCCESS;
    }
}
