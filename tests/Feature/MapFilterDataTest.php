<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QrarSource;
use App\Livewire\Dashboard\DistributionCharts;
use App\Livewire\Dashboard\OwnerPortfolios;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\EngineeringOffice;
use App\Models\Owner;
use App\Models\OwnerPortfolio;
use App\Models\Parcel;
use App\Models\ParcelBoundary;
use App\Models\Plan;
use App\Models\Region;
use App\Models\SurveyDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the data the dashboard map's "show these parcels" filter relies on:
 * geo.parcels needs a city_name on every feature (the map's own client-side
 * filter and city dropdown key off it), and each owner portfolio needs the
 * exact parcel ids it holds (the "show on map" button filters to just those).
 */
class MapFilterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_map_feed_includes_the_parcels_city_name(): void
    {
        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'القصيم']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'بريدة']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'الفهد']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $parcel = Parcel::create(['parcel_no' => '1', 'geo_id' => 'geo-1', 'plan_id' => $plan->id]);
        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $user = User::create([
            'name' => 'مستخدم', 'email' => 'geo@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('geo.parcels'))
            ->assertOk()
            ->assertJsonPath('features.0.properties.city_name', 'بريدة');
    }

    public function test_owner_portfolios_expose_the_exact_parcel_ids_they_hold(): void
    {
        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000001']);
        $portfolio = OwnerPortfolio::create(['owner_id' => $owner->id, 'name' => 'مشروع أ']);

        $inPortfolio = $this->makeParcel($plan->id, '1', $owner);
        $this->makeParcel($plan->id, '2', $owner); // held by the owner, not this portfolio

        DB::table('owner_portfolio_parcels')->insert([
            'owner_id' => $owner->id,
            'parcel_id' => $inPortfolio->id,
            'owner_portfolio_id' => $portfolio->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'مستخدم', 'email' => 'portfolios@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $portfolios = Livewire::actingAs($user)
            ->test(OwnerPortfolios::class)
            ->get('portfolios');

        $this->assertSame([$inPortfolio->id], $portfolios[0]['parcel_ids']);
    }

    private function makeParcel(int $planId, string $parcelNo, Owner $owner): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => $parcelNo, 'geo_id' => 'geo-'.$parcelNo, 'plan_id' => $planId]);

        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'deed-'.$parcelNo, 'deed_area' => 1000]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }

    public function test_the_map_feed_carries_the_office_and_survey_sources_a_chart_filters_by(): void
    {
        $parcel = Parcel::create(['parcel_no' => '2', 'geo_id' => 'geo-2']);
        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?", [$parcel->id]);
        $office = EngineeringOffice::create(['name' => 'مكتب الإسناد']);
        ParcelBoundary::create(['parcel_id' => $parcel->id, 'engineering_office_id' => $office->id]);
        SurveyDecision::create(['parcel_id' => $parcel->id, 'qrar_source' => QrarSource::Municipal->value]);
        SurveyDecision::create(['parcel_id' => $parcel->id, 'qrar_source' => QrarSource::Engineering->value]);

        $user = User::create(['name' => 'مستخدم', 'email' => 'geo2@sakuki.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $props = $this->actingAs($user)->getJson(route('geo.parcels'))->assertOk()->json('features.0.properties');
        $this->assertSame('مكتب الإسناد', $props['office_name']);
        $this->assertEqualsCanonicalizing(['بلدي', 'مكتب هندسي'], explode(',', $props['qrar_sources']));
    }

    public function test_the_district_chart_lists_every_district_not_only_the_top_ten(): void
    {
        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'القصيم']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'بريدة']);
        foreach (range(1, 12) as $i) {
            $district = District::create(['city_id' => $city->id, 'name_ar' => 'حي '.$i]);
            $plan = Plan::create(['plan_no' => 'p'.$i, 'district_id' => $district->id]);
            Parcel::create(['parcel_no' => (string) $i, 'geo_id' => 'g-'.$i, 'plan_id' => $plan->id]);
        }
        $user = User::create(['name' => 'مستخدم', 'email' => 'geo3@sakuki.test', 'password' => bcrypt('password'), 'is_active' => true]);

        $this->assertCount(12, Livewire::actingAs($user)->test(DistributionCharts::class)->get('byDistrict'));
    }
}
