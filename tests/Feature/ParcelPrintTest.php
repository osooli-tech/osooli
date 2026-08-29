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
use App\Services\Parcel\ParcelQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParcelPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_qr_code_encodes_the_parcels_twin_url(): void
    {
        $parcel = $this->makeParcel();

        $svg = app(ParcelQrCodeService::class)->svgFor($parcel);

        $this->assertStringStartsWith('<svg', trim($svg));
        $this->assertStringNotContainsString('<?xml', $svg);
    }

    public function test_printing_a_parcel_returns_a_pdf(): void
    {
        $parcel = $this->makeParcel();
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('parcels.print', $parcel));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    private function makeParcel(): Parcel
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

        return $parcel->refresh();
    }
}
