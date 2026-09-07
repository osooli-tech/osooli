<?php

declare(strict_types=1);

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * gdb_import.py defaults every new district to the fixed 'الدرعية' city
     * (correct for الأمير's data, since every one of those parcels really is
     * there). The دواجن الوطنية source reused the same importer but its
     * parcels are actually in Al-Qassim — confirmed against real coordinates
     * (e.g. "وادي عنيزة" centroids sit within ~1km of Unaizah's own centre).
     * Four districts (القصيباء-1، محير الترمس، كبد-1، كبد-2) had no
     * confidently-nearby reference city within ~70-120km of any point
     * this migration could verify, so they are placed under بريدة (the
     * provincial capital) as the least-wrong default — client accepted this
     * as a temporary placement, correct later if the true city is known.
     *
     * Written as a plain UPDATE keyed off "still points at الدرعية", so
     * running it again after it has already taken effect is a no-op rather
     * than a duplicate move.
     */
    public function up(): void
    {
        $diriyah = City::where('name_ar', 'الدرعية')->first();

        if ($diriyah === null) {
            return;
        }

        $country = Country::first();

        if ($country === null) {
            return;
        }

        $region = Region::firstOrCreate(['name_ar' => 'منطقة القصيم', 'country_id' => $country->id]);

        $cities = [];
        foreach (['بريدة', 'عنيزة', 'البكيرية'] as $name) {
            $cities[$name] = City::firstOrCreate(['name_ar' => $name, 'region_id' => $region->id]);
        }

        $map = [
            'أوثال' => 'بريدة',
            'بطين-1' => 'بريدة',
            'وادي عنيزة' => 'عنيزة',
            'ضلفعة-2' => 'البكيرية',
            'ضلفعة-3' => 'البكيرية',
            'القصيباء-1' => 'بريدة',
            'محير الترمس' => 'بريدة',
            'كبد-1' => 'بريدة',
            'كبد-2' => 'بريدة',
        ];

        foreach ($map as $districtName => $cityName) {
            District::where('name_ar', $districtName)
                ->where('city_id', $diriyah->id)
                ->update(['city_id' => $cities[$cityName]->id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not reversed to "الدرعية" — that would resurrect a known-wrong
     * assignment rather than an unknown-good original state.
     */
    public function down(): void {}
};
