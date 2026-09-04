<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeedStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Services\Owner\OwnerPortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class OwnerPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private OwnerPortfolioService $service;

    private Owner $owner;

    private Parcel $ownedParcel;

    private Parcel $foreignParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OwnerPortfolioService::class);

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $this->owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $otherOwner = Owner::create(['name' => 'مالك آخر', 'national_id' => '1000000002']);

        $this->ownedParcel = $this->makeParcel($plan->id, '101', 1500, $this->owner);
        $this->foreignParcel = $this->makeParcel($plan->id, '202', 1500, $otherOwner);
    }

    public function test_moving_a_parcel_to_a_new_portfolio_updates_rather_than_duplicates(): void
    {
        $first = $this->service->create($this->owner, 'مشروع أ');
        $second = $this->service->create($this->owner, 'مشروع ب');

        $this->service->assign($this->owner, $this->ownedParcel, $first);
        $this->service->assign($this->owner, $this->ownedParcel, $second);

        $this->assertSame(
            1,
            DB::table('owner_portfolio_parcels')
                ->where('owner_id', $this->owner->id)
                ->where('parcel_id', $this->ownedParcel->id)
                ->count()
        );

        $this->assertSame(0, $this->service->summary($first)['parcels']);
        $this->assertSame(1, $this->service->summary($second)['parcels']);
    }

    public function test_assigning_a_parcel_the_owner_does_not_hold_is_rejected(): void
    {
        $portfolio = $this->service->create($this->owner, 'مشروع أ');

        $this->expectException(InvalidArgumentException::class);
        $this->service->assign($this->owner, $this->foreignParcel, $portfolio);
    }

    public function test_deleting_a_portfolio_unassigns_its_parcels_without_deleting_them(): void
    {
        $portfolio = $this->service->create($this->owner, 'مشروع أ');
        $this->service->assign($this->owner, $this->ownedParcel, $portfolio);

        $this->service->delete($portfolio);

        $this->assertModelExists($this->ownedParcel);
        $this->assertSame(
            0,
            DB::table('owner_portfolio_parcels')->where('owner_id', $this->owner->id)->count()
        );
    }

    public function test_summary_counts_only_priced_parcels_toward_value(): void
    {
        $unpriced = $this->makeParcel(Plan::first()->id, '303', null, $this->owner);
        $portfolio = $this->service->create($this->owner, 'مشروع أ');

        $this->service->assign($this->owner, $this->ownedParcel, $portfolio);
        $this->service->assign($this->owner, $unpriced, $portfolio);

        $summary = $this->service->summary($portfolio);

        $this->assertSame(2, $summary['parcels']);
        $this->assertSame(1, $summary['priced']);
        $this->assertNotNull($summary['value']);
    }

    public function test_parcels_with_assignment_lists_every_owned_parcel_even_when_unassigned(): void
    {
        $rows = $this->service->parcelsWithAssignment($this->owner);

        $this->assertCount(1, $rows);
        $this->assertSame($this->ownedParcel->id, $rows[0]['parcel']->id);
        $this->assertNull($rows[0]['portfolio_id']);
    }

    private function makeParcel(int $planId, string $parcelNo, ?float $price, Owner $owner): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => 'geo-'.$parcelNo,
            'plan_id' => $planId,
            'm_price' => $price,
        ]);

        DB::update(
            "UPDATE parcels SET geom = ST_SetSRID(ST_GeomFromText(
                'MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))'
             ), 4326) WHERE id = ?",
            [$parcel->id]
        );

        // Owner::parcels() only counts a parcel's active deed (deed_status =
        // Updated) as currently held — a superseded deed stays on record for
        // history but must not count toward the owner who no longer holds it.
        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => 'deed-'.$parcelNo,
            'deed_area' => 1000,
            'deed_status' => DeedStatus::Updated->value,
        ]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
