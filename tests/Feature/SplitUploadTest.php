<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Documents\SplitUpload;
use App\Models\ParcelPhoto;
use App\Models\User;
use App\Services\Import\ParcelGeoJsonImporter;
use App\Support\Import\MapPageMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A multi-parcel PDF split page by page: each page's text matched to the
 * parcel it names, each part stored against it — and, for the geodatabase
 * that brings those parcels, a district named by where they lie and an
 * engineering office added by name.
 */
class SplitUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Text as ArcGIS Pro stores it in the client's map series (page 1 of
     * 623_image.pdf): numbers before their labels, a deed number and a year
     * standing right next to "رقم القطعة".
     */
    private const PAGE_TEXT = "GS-84المجسم الناقص الدوراني العالمي\n)131الخريطة الكنتورية للقطعة رقم\n)623من المخطط رقم\n27.1\n25\n"
        ."البلدية رقم\nرقم الصك:\nتاريخ الصك:\n3/295\nرقم القطعة\n1435-03-08\nالمالك فيصل\nالمساحة :33534.51م2\n132قطعة رقم";

    private int $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = DB::table('plans')->insertGetId(['plan_no' => '623', 'created_at' => now(), 'updated_at' => now()]);
        // 132 is the neighbour in the boundary table; 295 and 1435 are a deed
        // number and a year. None of them may be taken for the page's parcel.
        foreach (['131', '132', '295', '1435'] as $no) {
            DB::table('parcels')->insert(['geo_id' => $no.'-623', 'parcel_no' => $no, 'plan_id' => $this->plan, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_the_page_text_names_its_parcel(): void
    {
        $scores = MapPageMatcher::scores(self::PAGE_TEXT);
        arsort($scores['parcel']);
        $this->assertSame('131', (string) array_key_first($scores['parcel']));

        $this->actingAs($this->uploader());
        $match = Livewire::test(SplitUpload::class)->instance()->matchPage(self::PAGE_TEXT);

        $this->assertSame((int) DB::table('parcels')->where('parcel_no', '131')->value('id'), $match['id']);
        $this->assertNull(Livewire::test(SplitUpload::class)->instance()->matchPage('صفحة بلا أرقام'));
    }

    public function test_an_image_only_page_is_matched_by_the_geo_id_ocr_reads(): void
    {
        $this->actingAs($this->uploader());

        // What Tesseract returned for page 1 of the client's 623.pdf (digits
        // and dashes only): the GEO ID among coordinates, a date, noise.
        $ocr = '0 363 4-- 1207531010718424 131-623 85 1 52 53 4 16557 4208 1929 - 39 7 2 0-8 42-8438';
        $match = Livewire::test(SplitUpload::class)->instance()->matchPage($ocr);

        $this->assertSame((int) DB::table('parcels')->where('geo_id', '131-623')->value('id'), $match['id']);
    }

    public function test_a_part_is_stored_pending_against_its_parcel(): void
    {
        Storage::fake(ParcelPhoto::PRIVATE_DISK);
        $this->actingAs($this->uploader());
        $parcel = (int) DB::table('parcels')->where('parcel_no', '131')->value('id');

        Livewire::test(SplitUpload::class)
            ->set('piece', UploadedFile::fake()->create('part.pdf', 200, 'application/pdf'))
            ->call('savePiece', ['parcel_id' => $parcel, 'photo_type' => 'كروكي مساحي', 'pages' => '1', 'source' => '623_image.pdf'])
            ->assertHasNoErrors();

        $photo = ParcelPhoto::sole();
        $this->assertSame($parcel, (int) $photo->parcel_id);
        $this->assertSame(ParcelPhoto::STATUS_PENDING, $photo->status);
        $this->assertSame('623_image — ص 1.pdf', $photo->original_name);
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->assertExists($photo->photo_url);

        $this->actingAs(User::factory()->create(['is_active' => true]));
        Livewire::test(SplitUpload::class)->assertForbidden();
    }

    public function test_parcels_with_no_district_name_go_to_the_district_named_for_them_and_a_new_office(): void
    {
        $country = DB::table('countries')->insertGetId(['name_ar' => 'السعودية', 'created_at' => now(), 'updated_at' => now()]);
        $region = DB::table('regions')->insertGetId(['name_ar' => 'منطقة الرياض', 'country_id' => $country, 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('cities')->insertGetId(['name_ar' => 'العمارية', 'region_id' => $region, 'created_at' => now(), 'updated_at' => now()]);

        $feature = ['type' => 'Feature', 'geometry' => null, 'properties' => [
            'Geo_ID' => '29-623', 'Parcel' => '29', 'Plan_No' => '623', 'District' => null, 'N_Border' => 'شارع 20 م', 'Survey_Area' => 25000,
        ]];

        $result = app(ParcelGeoJsonImporter::class)->importFeatures([$feature], [
            'districts' => [['name' => '', 'new_name' => 'العمارية', 'city_id' => $city, 'district_id' => null]],
            'office_name' => 'سيف للاستشارات الهندسية',
        ]);

        $this->assertSame(0, $result->errors);
        $district = DB::table('parcels as p')->join('plans as pl', 'pl.id', '=', 'p.plan_id')->join('districts as d', 'd.id', '=', 'pl.district_id')
            ->where('p.geo_id', '29-623')->first(['d.name_ar', 'd.city_id']);
        $this->assertSame(['العمارية', $city], [$district->name_ar, (int) $district->city_id]);

        $office = DB::table('engineering_offices')->where('name', 'سيف للاستشارات الهندسية')->value('id');
        $this->assertNotNull($office);
        $parcel = DB::table('parcels')->where('geo_id', '29-623')->value('id');
        $this->assertSame((int) $office, (int) DB::table('parcel_boundaries')->where('parcel_id', $parcel)->value('engineering_office_id'));
    }

    private function uploader(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'documents.upload', 'guard_name' => 'web']);
        $user->givePermissionTo('documents.upload');

        return $user;
    }
}
