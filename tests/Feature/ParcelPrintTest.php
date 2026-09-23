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
use App\Models\ParcelBoundary;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Services\Parcel\ParcelMapSvgService;
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

    /**
     * Regression test for a real production report that ran to two pages:
     * a pentagon parcel (5 real corners, not the placeholder "could not be
     * determined" text), real boundary descriptions, a long owner name, and
     * an actual rendered neighbours map — each looked fine in isolation
     * against a bare-minimum parcel, but together pushed the footer onto a
     * second page. ParcelMapSvgService needs Imagick and real neighbouring
     * parcels to produce a real image locally, so it's faked here to return
     * one without either.
     */
    public function test_the_report_stays_one_page_with_realistic_full_content(): void
    {
        $parcel = $this->makeParcel();
        ParcelBoundary::create([
            'parcel_id' => $parcel->id,
            'n_border' => 'قطعة رقم 163', 's_border' => 'قطعة رقم 165',
            'e_border' => 'وادي', 'w_border' => 'شارع عرض 20 متر',
            'n_dim' => 209, 's_dim' => 195, 'e_dim' => 188, 'w_dim' => 110,
        ]);
        Owner::whereKey(Deed::where('parcel_id', $parcel->id)->firstOrFail()->owners->first()->id)
            ->update(['name' => 'مساعد بن عبدالرحمن آل سعود الأمير فيصل بن']);

        DB::update(
            "UPDATE parcels SET geom = ST_SetSRID(ST_GeomFromText(
                'MULTIPOLYGON(((46.34 24.71, 46.345 24.712, 46.348 24.708, 46.344 24.704, 46.339 24.706, 46.34 24.71)))'
             ), 4326) WHERE id = ?",
            [$parcel->id]
        );

        // A real rendered map is a 230x230 image; without one (no Imagick, or
        // no real neighbours), the report falls back to a much shorter text
        // placeholder that hides this whole class of overflow.
        $im = imagecreatetruecolor(230, 230);
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 200, 220, 210));
        ob_start();
        imagepng($im);
        $mapImage = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $this->mock(ParcelMapSvgService::class, function ($mock) use ($mapImage): void {
            $mock->shouldReceive('render')->andReturn($mapImage);
        });

        $user = User::factory()->create(['is_active' => true]);
        $response = $this->actingAs($user)->get(route('parcels.print', $parcel));

        $response->assertOk();
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
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'deed-101', 'deed_area' => 1000]);
        $owner = Owner::create(['name' => 'مالك تجريبي', 'national_id' => '1000000001']);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
