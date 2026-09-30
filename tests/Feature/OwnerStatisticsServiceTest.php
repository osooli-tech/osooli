<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeedStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\EngineeringOffice;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelBoundary;
use App\Models\Plan;
use App\Models\Region;
use App\Models\SurveyDecision;
use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The extra aggregates the owner portal's richer dashboard needs on top of
 * summary()/portfolio() — each scoped the same way, to this owner's parcels
 * alone, never another owner's.
 */
class OwnerStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    private Owner $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $this->owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $this->otherOwner = Owner::create(['name' => 'مالك آخر', 'national_id' => '1000000002']);

        $office = EngineeringOffice::create(['name' => 'مكتب الرياض الهندسي']);

        $small = $this->makeParcel($plan->id, '101', 'geo-101', $this->owner, 'أرض', 500);
        $large = $this->makeParcel($plan->id, '102', 'geo-102', $this->owner, 'فيلا', 1500);
        $this->makeParcel($plan->id, '999', 'geo-999', $this->otherOwner, 'أرض', 9999);

        ParcelBoundary::create(['parcel_id' => $small->id, 'engineering_office_id' => $office->id]);
        SurveyDecision::create(['parcel_id' => $large->id, 'qrar_source' => 'بلدي']);
    }

    public function test_area_extremes_cover_only_the_owners_own_deeds(): void
    {
        $extremes = (new OwnerStatisticsService($this->owner))->areaExtremes();

        $this->assertSame(2000.0, $extremes['total']);
        $this->assertSame(1500.0, $extremes['max']);
        $this->assertSame(500.0, $extremes['min']);
        $this->assertSame(1000.0, $extremes['avg']);
    }

    public function test_plans_count_reflects_distinct_plans_only(): void
    {
        $this->assertSame(1, (new OwnerStatisticsService($this->owner))->plansCount());
    }

    public function test_by_asset_type_groups_the_owners_parcels(): void
    {
        $byType = (new OwnerStatisticsService($this->owner))->byAssetType();

        $this->assertEqualsCanonicalizing(
            ['أرض' => 1, 'فيلا' => 1],
            collect($byType)->pluck('parcels_count', 'name')->all()
        );
    }

    public function test_by_qrar_source_counts_only_the_owners_survey_decisions(): void
    {
        $bySource = (new OwnerStatisticsService($this->owner))->byQrarSource();

        $this->assertSame([['name' => 'بلدي', 'parcels_count' => 1]], $bySource);
    }

    public function test_by_engineering_office_counts_only_the_owners_boundaries(): void
    {
        $byOffice = (new OwnerStatisticsService($this->owner))->byEngineeringOffice();

        $this->assertSame([['name' => 'مكتب الرياض الهندسي', 'parcels_count' => 1]], $byOffice);
    }

    private function makeParcel(int $planId, string $parcelNo, string $geoId, Owner $owner, string $assetType, float $area): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => $geoId,
            'plan_id' => $planId,
            'asset_type' => $assetType,
        ]);

        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => "deed-{$parcelNo}",
            'deed_area' => $area,
            'deed_status' => DeedStatus::Updated->value,
        ]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
