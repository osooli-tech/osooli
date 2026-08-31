<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OwnerExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_excel_export_requires_the_exports_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('owners.export.excel'));

        $response->assertForbidden();
    }

    public function test_owners_excel_export_downloads_for_an_authorised_user(): void
    {
        $this->makeOwner();
        $user = $this->authorisedUser();

        $response = $this->actingAs($user)->get(route('owners.export.excel'));

        $response->assertOk();
    }

    public function test_owner_pdf_report_downloads_for_an_authorised_user(): void
    {
        $owner = $this->makeOwner();
        $user = $this->authorisedUser();

        $response = $this->actingAs($user)->get(route('owners.print', $owner));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_owner_pdf_report_works_for_an_owner_with_no_deeds(): void
    {
        $owner = Owner::create(['name' => 'مالك بلا صكوك', 'national_id' => '1000000002']);
        $user = $this->authorisedUser();

        $response = $this->actingAs($user)->get(route('owners.print', $owner));

        $response->assertOk();
    }

    private function authorisedUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::findOrCreate('exports.create', 'web');
        $user->givePermissionTo('exports.create');

        return $user;
    }

    private function makeOwner(): Owner
    {
        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $parcel = Parcel::create(['parcel_no' => '101', 'geo_id' => 'geo-101', 'plan_id' => $plan->id]);

        DB::update(
            "UPDATE parcels SET geom = ST_SetSRID(ST_GeomFromText(
                'MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))'
             ), 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'deed-101', 'deed_area' => 1000]);
        $owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $deed->owners()->attach($owner->id);

        return $owner->refresh();
    }
}
