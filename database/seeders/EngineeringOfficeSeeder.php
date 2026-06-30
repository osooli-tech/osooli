<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EngineeringOffice;
use App\Models\ParcelBoundary;
use Illuminate\Database\Seeder;

class EngineeringOfficeSeeder extends Seeder
{
    public function run(): void
    {
        $office = EngineeringOffice::firstOrCreate([
            'name' => 'مكتب الإسناد العالمي للاستشارات الهندسية',
        ]);

        $updated = ParcelBoundary::whereNull('engineering_office_id')
            ->update(['engineering_office_id' => $office->id]);

        $this->command->info("Default engineering office assigned to {$updated} parcel boundaries.");
    }
}
