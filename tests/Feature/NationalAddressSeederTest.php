<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\NationalAddressSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NationalAddressSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_whole_hierarchy_and_completes_existing_records(): void
    {
        // What the survey import leaves behind: Arabic names only, plus a
        // district no official list knows.
        $country = DB::table('countries')->insertGetId(['name_ar' => 'المملكة العربية السعودية', 'created_at' => now(), 'updated_at' => now()]);
        $region = DB::table('regions')->insertGetId(['country_id' => $country, 'name_ar' => 'منطقة القصيم', 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('cities')->insertGetId(['region_id' => $region, 'name_ar' => 'بريده', 'created_at' => now(), 'updated_at' => now()]);
        $survey = DB::table('districts')->insertGetId(['city_id' => $city, 'name_ar' => 'كبد-1', 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(NationalAddressSeeder::class);

        $this->assertSame(1, DB::table('countries')->count());
        $this->assertSame(13, DB::table('regions')->count());
        $this->assertSame(4581, DB::table('cities')->count());
        $this->assertSame(3733, DB::table('districts')->count());

        $this->assertSame('SA', DB::table('countries')->value('iso_code'));
        $this->assertSame('Qassim', DB::table('regions')->where('id', $region)->value('name_en'));
        // Matched despite «بريده» for «بريدة»; its Arabic spelling is kept.
        $this->assertSame('Buraidah', DB::table('cities')->where('id', $city)->value('name_en'));
        $this->assertSame('بريده', DB::table('cities')->where('id', $city)->value('name_ar'));
        $this->assertSame(0, DB::table('regions')->whereNull('name_en')->count());

        // The survey district is kept, untouched, under the matched city.
        $this->assertDatabaseHas('districts', ['id' => $survey, 'city_id' => $city, 'name_en' => null, 'national_address_id' => null]);

        // New districts are stored without the «حي» / «Dist.» the source adds.
        $this->assertDatabaseHas('districts', ['national_address_id' => 10100003001, 'name_ar' => 'العمل', 'name_en' => 'Al Amal']);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(NationalAddressSeeder::class);
        $counts = [DB::table('regions')->count(), DB::table('cities')->count(), DB::table('districts')->count()];

        $this->seed(NationalAddressSeeder::class);

        $this->assertSame($counts, [DB::table('regions')->count(), DB::table('cities')->count(), DB::table('districts')->count()]);
    }
}
