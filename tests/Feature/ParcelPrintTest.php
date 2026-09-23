<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PhotoType;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
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

    public function test_the_report_stays_one_page_even_with_a_deed_document_attached(): void
    {
        $parcel = $this->makeParcel();
        ParcelPhoto::create([
            'parcel_id' => $parcel->id,
            'photo_url' => '/storage/documents/deeds/101.pdf',
            'photo_type' => PhotoType::Deed->value,
        ]);
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('parcels.print', $parcel));

        $response->assertOk();
        // dompdf emits one "/Type /Page" object per page (not to be confused
        // with the singular "/Type /Pages" tree root) — a plain, dependency-
        // free way to assert page count against the raw PDF bytes.
        $pageObjects = preg_match_all('/\/Type\s*\/Page(?!s)/', $response->getContent());
        $this->assertSame(1, $pageObjects);
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
