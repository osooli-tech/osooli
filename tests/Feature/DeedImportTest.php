<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Imports\ImportCenter;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\SurveyDecision;
use App\Models\User;
use App\Support\Export\DeedExportFilters;
use App\Support\Export\DeedGeoJsonExporter;
use App\Support\Export\ExportRuns;
use App\Support\Import\DeedImportAnalyzer;
use App\Support\Import\DeedImportApplier;
use App\Support\Import\DeedImportUndo;
use App\Support\Import\GeoJsonFeatureStream;
use App\Support\Import\ImportRuns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeedImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Deed $deed;

    private Owner $salem;

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        foreach (['imports.run', 'exports.bulk'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo(['imports.run', 'exports.bulk']);
        $this->actingAs($this->user);

        $country = Country::create(['name_ar' => 'المملكة العربية السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الرياض']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'الملقا']);
        $plan = Plan::create(['plan_no' => 'P-7', 'district_id' => $district->id]);

        $parcel = Parcel::create(['geo_id' => 'GEO-1', 'parcel_no' => '101', 'plan_id' => $plan->id]);
        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.6 24.7, 46.601 24.7, 46.601 24.701, 46.6 24.701, 46.6 24.7)))', 4326), asset_type = 'أرض' WHERE id = ?",
            [$parcel->id]
        );
        $this->deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => '310101000001', 'deed_area' => 11200]);
        DB::update("UPDATE deeds SET deed_status = 'محدث' WHERE id = ?", [$this->deed->id]);
        $this->salem = Owner::create(['name' => 'سالم أحمد', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $this->deed->owners()->attach($this->salem->id, ['ownership_share' => 100]);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            foreach (glob($path.'/*') ?: [] as $file) {
                @unlink($file);
            }
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }

        parent::tearDown();
    }

    public function test_an_unedited_export_imports_as_all_unchanged(): void
    {
        $run = $this->analyse($this->exported());

        $this->assertSame('analysed', $run['state'], (string) ($run['error'] ?? ''));
        $this->assertSame(1, $run['counts']['same']);
        $this->assertSame(0, $run['counts']['changed'] + $run['counts']['new'] + $run['counts']['error']);
    }

    public function test_edits_are_shown_field_by_field_applied_and_undone(): void
    {
        $file = $this->exported();
        $file['features'][0]['properties']['deed_area'] = 12000;
        $file['features'][0]['properties']['owner_1_phone'] = '0555555555';

        $run = $this->analyse($file);
        $item = $this->items($run['id'])[0];

        $this->assertSame('changed', $item['status']);
        $this->assertEquals([11200, 12000], $item['deed']['changes']['deed_area']);
        $this->assertSame('update', $item['owners'][0]['action']);

        $this->apply($run['id']);
        $this->assertEquals(12000, (float) $this->deed->fresh()->deed_area);
        $this->assertSame('0555555555', $this->salem->fresh()->phone);

        $undone = app(DeedImportUndo::class)->run($run['id'], $this->user);
        $this->assertSame('undone', $undone['state']);
        $this->assertEquals(11200, (float) $this->deed->fresh()->deed_area);
        $this->assertSame('0500000001', $this->salem->fresh()->phone);
    }

    public function test_a_new_deed_with_a_new_owner_is_created_and_undo_removes_it(): void
    {
        $file = $this->exported();
        $new = $file['features'][0];
        $new['id'] = 'new';
        $new['properties'] = ['deed_id' => null, 'deed_no' => '999999', 'deed_area' => 500, 'deed_status' => 'قديم'] + $new['properties'];
        // Every owner column emptied, then the first set filled with someone new.
        foreach (array_keys($new['properties']) as $column) {
            if (str_starts_with($column, 'owner_')) {
                $new['properties'][$column] = null;
            }
        }
        $new['properties'] = ['owner_1_name' => 'مالك جديد تماما', 'owner_1_national_id' => '2000000000', 'owner_1_share' => 100] + $new['properties'];
        $file['features'][] = $new;

        $run = $this->analyse($file);
        $this->assertSame(1, $run['counts']['new']);

        $this->apply($run['id']);
        $this->assertDatabaseHas('deeds', ['deed_no' => '999999']);
        $this->assertDatabaseHas('owners', ['national_id' => '2000000000']);

        app(DeedImportUndo::class)->run($run['id'], $this->user);
        $this->assertDatabaseMissing('deeds', ['deed_no' => '999999']);
        $this->assertDatabaseMissing('owners', ['national_id' => '2000000000']);
    }

    public function test_a_look_alike_owner_waits_for_a_decision(): void
    {
        $file = $this->exported();
        // One digit off Salem's national ID: the usual typing slip.
        // Added as one more numbered owner set, after the ones the export wrote.
        $file['features'][0]['properties'] += ['owner_9_name' => 'سالم احمد', 'owner_9_national_id' => '1000000007', 'owner_9_share' => 0];

        $run = $this->analyse($file);
        $key = array_key_first($run['decisions_needed']);

        $this->assertSame('decision', $this->items($run['id'])[0]['status']);
        $this->assertSame('owner', $run['decisions_needed'][$key]['type']);
        $this->assertSame($this->salem->id, $run['decisions_needed'][$key]['candidates'][0]['id']);
        $this->assertContains('nid_close', $run['decisions_needed'][$key]['candidates'][0]['reasons']);

        // "It is Salem": no new owner is created.
        $this->apply($run['id'], [$key => $this->salem->id]);
        $this->assertDatabaseMissing('owners', ['national_id' => '1000000007']);
    }

    public function test_an_unknown_district_can_be_created_on_decision(): void
    {
        $file = $this->exported();
        $file['features'][0]['properties']['plan_no'] = 'P-NEW';
        $file['features'][0]['properties']['district'] = 'حي النخيل الجديد';

        $run = $this->analyse($file);
        $key = array_key_first($run['decisions_needed']);
        $this->assertSame('district', $run['decisions_needed'][$key]['type']);

        $this->apply($run['id'], [$key => 'create']);
        $this->assertDatabaseHas('districts', ['name_ar' => 'حي النخيل الجديد']);
        $this->assertSame('P-NEW', Parcel::where('geo_id', 'GEO-1')->first()->plan->plan_no);
    }

    public function test_bad_rows_are_errors_and_are_never_written(): void
    {
        $file = $this->exported();
        $file['features'][0]['properties']['deed_status'] = 'غير موجود';

        // A deed_id that belongs to a deed on another parcel.
        $elsewhere = $this->exported()['features'][0];
        $elsewhere['properties']['parcel_geo_id'] = 'GEO-OTHER';
        $file['features'][] = $elsewhere;

        $run = $this->analyse($file);
        $items = $this->items($run['id']);

        $this->assertSame('error', $items[0]['status']);
        $this->assertSame('not_in_list', $items[0]['errors'][0]['code']);
        $this->assertSame('error', $items[1]['status']);
        $this->assertContains('deed_on_other_parcel', array_column($items[1]['errors'], 'code'));

        $this->apply($run['id']);
        $this->assertDatabaseMissing('parcels', ['geo_id' => 'GEO-OTHER']);
    }

    public function test_undo_keeps_a_row_edited_by_hand_after_the_import(): void
    {
        $file = $this->exported();
        $file['features'][0]['properties']['deed_area'] = 12000;

        $run = $this->analyse($file);
        $this->apply($run['id']);

        DB::table('deeds')->where('id', $this->deed->id)->update(['deed_area' => 13000, 'updated_at' => now()->addMinute()]);

        $undone = app(DeedImportUndo::class)->run($run['id'], $this->user);
        $this->assertEquals(13000, (float) $this->deed->fresh()->deed_area);
        $this->assertContains('deeds#'.$this->deed->id, $undone['undo_result']['kept']);
    }

    public function test_a_copied_row_that_keeps_the_original_deed_id_is_refused(): void
    {
        $file = $this->exported();
        $copy = $file['features'][0];
        $copy['properties']['deed_no'] = '555555';
        $file['features'][] = $copy;

        $run = $this->analyse($file);
        $codes = array_column($this->items($run['id'])[1]['errors'], 'code');

        $this->assertContains('deed_id_duplicate_in_file', $codes);
        $this->assertContains('deed_id_number_mismatch', $codes);

        $this->apply($run['id']);
        $this->assertSame('310101000001', $this->deed->fresh()->deed_no);
    }

    public function test_a_parcel_alone_is_imported_without_creating_a_deed(): void
    {
        $file = $this->exported();
        $file['features'][] = [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => ['parcel_geo_id' => 'LAND-ONLY', 'parcel_no' => '777', 'deed' => null],
        ];

        $run = $this->analyse($file);
        $item = $this->items($run['id'])[1];

        $this->assertSame('new', $item['status']);
        $this->assertSame('none', $item['deed']['action']);

        $this->apply($run['id']);
        $parcel = Parcel::where('geo_id', 'LAND-ONLY')->first();
        $this->assertNotNull($parcel);
        $this->assertSame(0, Deed::where('parcel_id', $parcel->id)->count());
    }

    public function test_a_table_exported_straight_from_gis_is_read_by_its_plain_columns(): void
    {
        $file = $this->exported();
        $file['features'][] = [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [
                'geo_id' => 'GIS-240-11', 'parcel_no' => '11', 'fall_in' => 'الصك',
                'n_border' => 'قطعة رقم 10', 'n_dim' => 405.84, 'deed_no' => '262903006602', 'deed_area' => 51750,
            ],
        ];

        $run = $this->analyse($file);
        $item = $this->items($run['id'])[1];

        $this->assertNotContains('geo_id_missing', array_column($item['errors'], 'code'));
        $this->assertSame('GIS-240-11', $item['parcel']['geo_id']);

        $this->apply($run['id']);
        $parcel = Parcel::where('geo_id', 'GIS-240-11')->first();
        $this->assertNotNull($parcel);
        $this->assertSame('الصك', (string) ($parcel->fall_in?->value ?? $parcel->fall_in));
        $this->assertSame(1, Deed::where('parcel_id', $parcel->id)->where('deed_no', '262903006602')->count());
    }

    public function test_a_unit_is_linked_to_a_parent_described_later_in_the_file(): void
    {
        $file = $this->exported();
        $file['features'][] = ['type' => 'Feature', 'geometry' => null,
            'properties' => ['parcel_geo_id' => 'FLAT-1', 'parent_geo_id' => 'TOWER-1']];
        $file['features'][] = ['type' => 'Feature', 'geometry' => null,
            'properties' => ['parcel_geo_id' => 'TOWER-1']];
        $file['features'][] = ['type' => 'Feature', 'geometry' => null,
            'properties' => ['parcel_geo_id' => 'FLAT-2', 'parent_geo_id' => 'NOWHERE']];

        $run = $this->analyse($file);
        $this->assertSame([3 => 'NOWHERE'], $run['unresolved_parents']);

        $this->apply($run['id']);
        $tower = Parcel::where('geo_id', 'TOWER-1')->value('id');
        $this->assertSame($tower, Parcel::where('geo_id', 'FLAT-1')->value('parent_parcel_id'));
        $this->assertNull(Parcel::where('geo_id', 'FLAT-2')->value('parent_parcel_id'));

        // Undo removes the units and their link with them.
        app(DeedImportUndo::class)->run($run['id'], $this->user);
        $this->assertDatabaseMissing('parcels', ['geo_id' => 'FLAT-1']);
        $this->assertDatabaseMissing('parcels', ['geo_id' => 'TOWER-1']);
    }

    public function test_the_engineering_office_and_deed_match_are_imported(): void
    {
        $file = $this->exported();
        $file['features'][0]['properties'] = [
            'n_border' => 'شارع 30م',
            'n_dim' => 25,
            'matches_deed' => 'نعم',
            'engineering_office' => 'مكتب الرؤية للاستشارات',
        ] + $file['features'][0]['properties'];

        $run = $this->analyse($file);
        $key = array_key_first($run['decisions_needed']);
        $this->assertSame('office', $run['decisions_needed'][$key]['type']);

        $this->apply($run['id'], [$key => 'create']);
        $office = DB::table('engineering_offices')->where('name', 'مكتب الرؤية للاستشارات')->value('id');
        $boundary = DB::table('parcel_boundaries')->where('parcel_id', $this->deed->parcel_id)->first();

        $this->assertNotNull($office);
        $this->assertSame((int) $office, (int) $boundary->engineering_office_id);
        $this->assertTrue((bool) $boundary->matches_deed);

        app(DeedImportUndo::class)->run($run['id'], $this->user);
        $this->assertDatabaseMissing('engineering_offices', ['name' => 'مكتب الرؤية للاستشارات']);
    }

    public function test_two_places_with_one_name_in_a_region_are_told_apart(): void
    {
        // As with the two «الدرعية» in the National Address: a second
        // place of the same name, in the same region, with no districts.
        $region = Region::where('name_ar', 'منطقة الرياض')->first();
        City::create(['region_id' => $region->id, 'name_ar' => 'الرياض']);

        // Unedited export: the district settles which «الرياض» is meant.
        $run = $this->analyse($this->exported());
        $this->assertSame(0, $run['counts']['error']);

        // No district, a new plan: the city with districts is taken.
        $file = $this->exported();
        $file['features'][0]['properties']['district'] = null;
        $file['features'][0]['properties']['plan_no'] = 'P-NEW-2';
        $run = $this->analyse($file);
        $this->assertSame(0, $run['counts']['error']);
    }

    public function test_one_deed_number_on_several_parcels_is_not_a_duplicate(): void
    {
        $file = $this->exported();

        // The deed's number on a second parcel: a deed row of its own there.
        $second = $file['features'][0];
        $second['properties']['deed_id'] = null;
        $second['properties']['parcel_geo_id'] = 'GEO-2ND';
        $file['features'][] = $second;

        // The very same deed, number and parcel, twice: that is a duplicate.
        $twice = $second;
        $file['features'][] = $twice;

        $run = $this->analyse($file);
        $items = $this->items($run['id']);

        $this->assertSame('same', $items[0]['status']);
        $this->assertSame('new', $items[1]['status']);
        $this->assertSame('error', $items[2]['status']);
        $this->assertSame('deed_duplicate_in_file', $items[2]['errors'][0]['code']);

        $this->apply($run['id']);
        $this->assertSame(2, Deed::where('deed_no', '310101000001')->count());
    }

    public function test_the_stream_reads_a_pretty_printed_file_and_its_header(): void
    {
        $path = storage_path('app/stream-test.geojson');
        $this->cleanup[] = $path;
        file_put_contents($path, json_encode([
            'type' => 'FeatureCollection',
            'sokuki' => ['format' => 'sokuki-deeds', 'version' => 1],
            'features' => [
                ['type' => 'Feature', 'properties' => ['deed_no' => 'a "quoted" {brace}'], 'geometry' => null],
                ['type' => 'Feature', 'properties' => ['deed_no' => 'ب'], 'geometry' => null],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $stream = new GeoJsonFeatureStream($path);
        $features = iterator_to_array($stream->features());

        $this->assertCount(2, $features);
        $this->assertSame('a "quoted" {brace}', $features[0]['properties']['deed_no']);
        $this->assertSame('sokuki-deeds', $stream->header()['sokuki']['format']);
    }

    public function test_the_page_uploads_analyses_and_applies(): void
    {
        $this->withoutDefer();
        $file = $this->exported();
        $file['features'][0]['properties']['deed_area'] = 12500;

        $page = Livewire::test(ImportCenter::class)
            ->set('upload', UploadedFile::fake()->createWithContent('deeds.geojson', (string) json_encode($file, JSON_UNESCAPED_UNICODE)))
            ->call('analyse');

        $id = (string) $page->get('following');
        $this->cleanup[] = ImportRuns::path($id);
        $this->assertSame('analysed', ImportRuns::find($id)['state']);

        $page->call('apply')->assertHasErrors('confirmed');
        $page->set('confirmed', true)->call('apply');

        $this->assertSame('applied', ImportRuns::find($id)['state']);
        $this->assertEquals(12500, (float) $this->deed->fresh()->deed_area);
    }

    /** @return array<string, mixed> the current data as the export page writes it */
    public function test_the_template_holds_one_sample_record_that_imports_as_it_is(): void
    {
        $response = $this->get(route('imports.template'))->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));

        $file = json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $file['features']);
        $this->assertSame('MultiPolygon', $file['features'][0]['geometry']['type']);
        $this->assertContains('الصك', $file['sokuki']['allowed_values']['fall_in']);

        $p = $file['features'][0]['properties'];
        $this->assertSame('DEMO-0001', $p['parcel_geo_id']);
        $this->assertSame('410100000001', $p['deed_no']);
        $this->assertSame('1098765432', $p['owner_1_national_id']);
        $this->assertSame('شارع عرض 20 م', $p['n_border']);
        $this->assertSame('12345', $p['survey_1_qrar_no']);
        $this->assertSame('صك-410100000001.pdf', $p['document_1_name']);
        $this->assertSame('الملقا', $p['district']);
        // Room for more: three owner sets and two of the others, left empty.
        $this->assertNull($p['owner_3_name']);
        $this->assertNull($p['survey_2_qrar_no']);
        $this->assertNull($p['document_2_name']);
        // One value per column, and no history to fill in.
        $this->assertSame([], array_filter($p, 'is_array'));
        $this->assertArrayNotHasKey('deed_created_at', $p);

        // Uploaded untouched, the sample is a clean record.
        $run = $this->analyse($file);
        $item = $this->items($run['id'])[0];
        $this->assertSame([], $item['errors'], json_encode($item['errors'], JSON_UNESCAPED_UNICODE));

        $this->apply($run['id']);
        $parcel = Parcel::where('geo_id', 'DEMO-0001')->firstOrFail();
        $this->assertSame(1, Deed::where('parcel_id', $parcel->id)->where('deed_no', '410100000001')->count());
        $this->assertSame(1, SurveyDecision::where('parcel_id', $parcel->id)->where('qrar_no', '12345')->count());
        $this->assertSame(1, Owner::where('national_id', '1098765432')->count());
    }

    public function test_a_file_in_the_earlier_nested_layout_still_imports(): void
    {
        $file = ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [
                'deed' => ['deed_no' => 'OLD-1', 'deed_area' => 300],
                'parcel' => ['geo_id' => 'OLD-GEO-1', 'parcel_no' => '7'],
                'owners' => [['name' => 'مالك من ملف قديم', 'national_id' => '1011111111', 'ownership_share' => 100]],
                'boundary' => ['north' => ['border' => 'شارع 12م', 'length' => 20]],
                'survey_decisions' => [['qrar_no' => 'OLD-Q', 'qrar_source' => 'بلدي']],
            ],
        ]]];

        $run = $this->analyse($file);
        $this->assertSame([], $this->items($run['id'])[0]['errors']);

        $this->apply($run['id']);
        $parcel = Parcel::where('geo_id', 'OLD-GEO-1')->firstOrFail();
        $this->assertSame(1, Deed::where('parcel_id', $parcel->id)->where('deed_no', 'OLD-1')->count());
        $this->assertDatabaseHas('owners', ['national_id' => '1011111111']);
        $this->assertDatabaseHas('parcel_boundaries', ['parcel_id' => $parcel->id, 'n_border' => 'شارع 12م']);
        $this->assertSame(1, SurveyDecision::where('parcel_id', $parcel->id)->where('qrar_no', 'OLD-Q')->count());
    }

    private function exported(): array
    {
        $id = ExportRuns::newId();
        app(DeedGeoJsonExporter::class)->run($id, new DeedExportFilters([]), DeedGeoJsonExporter::GROUPS, $this->user);
        $data = json_decode((string) file_get_contents(ExportRuns::filePath($id)), true);
        @unlink(ExportRuns::filePath($id));
        @unlink(ExportRuns::directory()."/{$id}.json");

        return $data;
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function analyse(array $file): array
    {
        $id = ImportRuns::newId();
        $this->cleanup[] = ImportRuns::path($id);
        file_put_contents(ImportRuns::path($id, 'source.geojson'), json_encode($file, JSON_UNESCAPED_UNICODE));
        ImportRuns::save(['id' => $id, 'state' => 'analysing', 'user_id' => $this->user->id, 'file' => 't.geojson', 'created_at' => now()->toIso8601String()]);

        return app(DeedImportAnalyzer::class)->run($id, $this->user);
    }

    /** @param  array<string, mixed>  $choices */
    private function apply(string $id, array $choices = []): void
    {
        $run = ImportRuns::find($id);
        $run['choices'] = $choices;
        ImportRuns::save($run);

        $applied = app(DeedImportApplier::class)->run($id, $this->user);
        $this->assertSame('applied', $applied['state'], (string) ($applied['error'] ?? ''));
    }

    /** @return list<array<string, mixed>> */
    private function items(string $id): array
    {
        return array_values(iterator_to_array(ImportRuns::items($id)));
    }
}
