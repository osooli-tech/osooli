<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The portal's whole point: an owner can only ever reach their own parcels,
 * the same rule OwnerParcelQuery already enforces for the mobile API.
 */
class PortalParcelsTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    private Owner $otherOwner;

    private Parcel $ownedParcel;

    private Parcel $foreignParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '25', 'district_id' => $district->id]);

        $this->owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $this->otherOwner = Owner::create(['name' => 'مالك آخر', 'national_id' => '1000000002']);

        $this->ownedParcel = $this->makeParcel($plan->id, '101', 'geo-101', $this->owner);
        $this->foreignParcel = $this->makeParcel($plan->id, '202', 'geo-202', $this->otherOwner);
    }

    public function test_the_parcel_list_shows_only_the_signed_in_owners_parcels(): void
    {
        $response = $this->actingAs($this->owner, 'owner')->get(route('portal.parcels.index'));

        $response->assertOk()
            ->assertSee($this->ownedParcel->parcel_no)
            ->assertDontSee($this->foreignParcel->parcel_no);
    }

    public function test_an_owner_can_open_their_own_parcel(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.show', $this->ownedParcel))
            ->assertOk();
    }

    public function test_an_owner_cannot_reach_another_owners_parcel(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.show', $this->foreignParcel))
            ->assertNotFound();
    }

    public function test_the_map_feed_only_carries_the_signed_in_owners_parcels(): void
    {
        $response = $this->actingAs($this->owner, 'owner')
            ->getJson(route('portal.geo.parcels'))
            ->assertOk();

        $geoIds = collect($response->json('features'))->pluck('properties.geo_id');

        $this->assertTrue($geoIds->contains('geo-101'));
        $this->assertFalse($geoIds->contains('geo-202'));
    }

    public function test_a_guest_cannot_reach_any_portal_page(): void
    {
        $this->get(route('portal.parcels.index'))->assertRedirect(route('portal.login'));
        $this->get(route('portal.parcels.show', $this->ownedParcel))->assertRedirect(route('portal.login'));
        // A JSON request gets the API-style 401 rather than a browser
        // redirect — the same way any other guarded endpoint on this app
        // answers a fetch() call from a guest.
        $this->getJson(route('portal.geo.parcels'))->assertUnauthorized();
    }

    private function makeParcel(int $planId, string $parcelNo, string $geoId, Owner $owner): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => $geoId,
            'plan_id' => $planId,
        ]);

        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => "deed-{$parcelNo}",
            'deed_area' => 1000,
            'deed_status' => DeedStatus::Updated->value,
        ]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
