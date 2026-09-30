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
use App\Services\Owner\OwnerInsightsService;
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

        // parcel_no alone is too short to assert on reliably here — "202" is
        // also a substring of any 2026 date the page renders. deed_no doesn't collide.
        $response->assertOk()
            ->assertSee('deed-101')
            ->assertDontSee('deed-202');
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
        // Every parcel carries its 3D category, so the type view never falls back blindly.
        $this->assertNotNull($response->json('features.0.properties.massing'));
    }

    public function test_an_owner_can_open_their_own_parcels_twin(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.twin', $this->ownedParcel))
            ->assertOk();
    }

    public function test_an_owner_cannot_reach_another_owners_twin(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.twin', $this->foreignParcel))
            ->assertNotFound();
    }

    public function test_printing_the_owners_own_parcel_returns_a_pdf(): void
    {
        $response = $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.print', $this->ownedParcel));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_an_owner_cannot_print_another_owners_parcel(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.print', $this->foreignParcel))
            ->assertNotFound();
    }

    public function test_an_owner_can_request_a_change_on_their_own_parcel(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('portal.parcels.modification-requests.store', $this->ownedParcel), [
                'field_name' => 'asset_type',
                'new_value' => 'شقة',
            ])
            ->assertRedirect(route('portal.parcels.show', $this->ownedParcel));

        $this->assertDatabaseHas('modification_requests', [
            'parcel_id' => $this->ownedParcel->id,
            'requested_by' => $this->owner->id,
            'field_name' => 'asset_type',
            'new_value' => 'شقة',
            'status' => 'pending',
        ]);
    }

    public function test_an_owner_cannot_request_a_change_on_another_owners_parcel(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('portal.parcels.modification-requests.store', $this->foreignParcel), [
                'field_name' => 'asset_type',
                'new_value' => 'شقة',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('modification_requests', 0);
    }

    public function test_a_change_request_only_accepts_the_editable_fields(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->post(route('portal.parcels.modification-requests.store', $this->ownedParcel), [
                'field_name' => 'parcel_no',
                'new_value' => '999',
            ])
            ->assertSessionHasErrors('field_name');
    }

    public function test_the_documents_feed_is_scoped_to_the_owner(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->getJson(route('portal.parcels.documents', $this->ownedParcel))
            ->assertOk()
            ->assertJsonStructure(['documents', 'images']);

        $this->actingAs($this->owner, 'owner')
            ->getJson(route('portal.parcels.documents', $this->foreignParcel))
            ->assertNotFound();
    }

    public function test_the_documents_requests_and_profile_pages_open(): void
    {
        foreach (['portal.documents.index', 'portal.modification-requests.index', 'portal.profile'] as $name) {
            $this->actingAs($this->owner, 'owner')->get(route($name))->assertOk();
        }
    }

    public function test_an_owner_can_update_their_contact_details_but_not_their_identity(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->put(route('portal.profile.update'), [
                'name' => 'اسم جديد',
                'email' => 'new@example.com',
                'whatsapp' => '0500000000',
                'national_id' => '9999999999',
            ])
            ->assertRedirect(route('portal.profile'));

        $fresh = $this->owner->fresh();
        $this->assertSame('اسم جديد', $fresh->name);
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertSame('1000000001', $fresh->national_id);
    }

    public function test_the_map_boundaries_feed_is_available_to_a_signed_in_owner(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->getJson(route('portal.geo.boundaries', 'districts'))
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection');
    }

    public function test_the_dashboard_insights_count_only_the_owners_own_parcels(): void
    {
        $insights = app(OwnerInsightsService::class)->for($this->owner);

        $this->assertSame(1, $insights['portfolio']['parcels']);
        $this->assertSame(['101'], array_column($insights['topParcels'], 'parcel_no'));
        $this->assertSame(1000.0, $insights['portfolio']['area']);
    }

    public function test_the_service_pages_render_inside_the_portal(): void
    {
        foreach (['survey-request', 'engineering-design', 'solar-energy', 'valuation', 'investment', 'municipal'] as $slug) {
            $this->actingAs($this->owner, 'owner')
                ->get(route('portal.services.'.$slug))
                ->assertOk();
        }
    }

    public function test_a_guest_cannot_reach_any_portal_page(): void
    {
        $this->get(route('portal.parcels.index'))->assertRedirect(route('portal.login'));
        $this->get(route('portal.parcels.show', $this->ownedParcel))->assertRedirect(route('portal.login'));
        $this->get(route('portal.parcels.twin', $this->ownedParcel))->assertRedirect(route('portal.login'));
        $this->get(route('portal.parcels.print', $this->ownedParcel))->assertRedirect(route('portal.login'));
        // A JSON request gets the API-style 401 rather than a browser
        // redirect — the same way any other guarded endpoint on this app
        // answers a fetch() call from a guest.
        $this->getJson(route('portal.geo.parcels'))->assertUnauthorized();
        $this->getJson(route('portal.geo.boundaries', 'districts'))->assertUnauthorized();
        $this->get(route('portal.profile'))->assertRedirect(route('portal.login'));
        $this->get(route('portal.documents.index'))->assertRedirect(route('portal.login'));
    }

    public function test_a_parcel_whose_deed_has_no_status_yet_still_belongs_to_its_owner(): void
    {
        $this->ownedParcel->deeds()->update(['deed_status' => null]);

        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.show', $this->ownedParcel->id))
            ->assertOk();
        $this->assertTrue($this->owner->parcels()->whereKey($this->ownedParcel->id)->exists());
    }

    public function test_a_parcel_whose_deed_is_old_no_longer_belongs_to_its_owner(): void
    {
        $this->ownedParcel->deeds()->update(['deed_status' => DeedStatus::Old->value]);

        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.parcels.show', $this->ownedParcel->id))
            ->assertNotFound();
    }

    public function test_a_parcel_reissued_to_a_new_owner_leaves_the_previous_one(): void
    {
        // A later deed with no status yet, issued to someone else, takes the parcel over.
        Deed::create(['parcel_id' => $this->ownedParcel->id, 'deed_no' => 'deed-new', 'deed_area' => 1000])
            ->owners()->attach($this->otherOwner->id);

        $this->assertFalse($this->owner->parcels()->whereKey($this->ownedParcel->id)->exists());
        $this->assertTrue($this->otherOwner->parcels()->whereKey($this->ownedParcel->id)->exists());
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
