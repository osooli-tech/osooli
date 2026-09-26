<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exports\OwnersWithoutDeedsExport;
use App\Livewire\Exports\ExportCenter;
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
use App\Models\SurveyDecision;
use App\Models\User;
use App\Support\Export\DeedExportFilters;
use App\Support\Export\DeedGeoJsonExporter;
use App\Support\Export\ExportRuns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeedExportTest extends TestCase
{
    use RefreshDatabase;

    private Deed $deed;

    private City $riyadh;

    /** @var list<string> */
    private array $runs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'المملكة العربية السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة الرياض', 'name_en' => 'Riyadh']);
        $this->riyadh = City::create(['region_id' => $region->id, 'name_ar' => 'الرياض', 'name_en' => 'Riyadh']);
        $district = District::create(['city_id' => $this->riyadh->id, 'name_ar' => 'الملقا', 'name_en' => 'Al Malqa']);
        $plan = Plan::create(['plan_no' => 'P-7', 'district_id' => $district->id]);

        $parcel = Parcel::create(['geo_id' => 'GEO-1', 'parcel_no' => '101', 'plan_id' => $plan->id, 'm_price' => 1500]);
        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.6 24.7, 46.601 24.7, 46.601 24.701, 46.6 24.701, 46.6 24.7)))', 4326), asset_type = 'أرض' WHERE id = ?",
            [$parcel->id]
        );

        $this->deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => '310101000001', 'deed_area' => 1000, 'deed_date_hijri' => '1445-03-10']);
        DB::update("UPDATE deeds SET deed_status = 'محدث', deed_class = 'سكني' WHERE id = ?", [$this->deed->id]);

        $a = Owner::create(['name' => 'سالم', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $b = Owner::create(['name' => 'نورة', 'national_id' => '1000000002']);
        $this->deed->owners()->attach([$a->id => ['ownership_share' => 60], $b->id => ['ownership_share' => 40]]);

        ParcelBoundary::create(['parcel_id' => $parcel->id, 'n_border' => 'شارع 20م', 'n_dim' => 30, 'measured_area' => 998.5]);
        SurveyDecision::create(['parcel_id' => $parcel->id, 'qrar_no' => 'Q-9', 'report_no' => 'R-1']);
        ParcelPhoto::create(['parcel_id' => $parcel->id, 'deed_id' => $this->deed->id, 'photo_url' => 'deeds/1.pdf', 'storage_disk' => 'documents', 'original_name' => 'صك.pdf', 'status' => ParcelPhoto::STATUS_APPROVED]);

        // A second deed elsewhere with no polygon, to be filtered out.
        $other = Parcel::create(['geo_id' => 'GEO-2', 'parcel_no' => '202']);
        Deed::create(['parcel_id' => $other->id, 'deed_no' => '999']);
    }

    protected function tearDown(): void
    {
        foreach ($this->runs as $id) {
            @unlink(ExportRuns::filePath($id));
            @unlink(ExportRuns::directory()."/{$id}.json");
        }

        parent::tearDown();
    }

    public function test_the_file_carries_every_part_of_the_deed(): void
    {
        $user = $this->exporter();
        $this->actingAs($user);

        $features = $this->export(['deed_no' => '310101'], $user)['features'];

        $this->assertCount(1, $features);
        $feature = $features[0];
        $p = $feature['properties'];

        $this->assertSame('MultiPolygon', $feature['geometry']['type']);
        $this->assertSame('310101000001', $p['deed_no']);
        $this->assertSame('محدث', $p['deed_status']);
        $this->assertSame('GEO-1', $p['parcel_geo_id']);
        $this->assertSame('الرياض', $p['city']);
        $this->assertSame('Riyadh', $p['region_en']);
        $this->assertEqualsWithDelta(11209, $p['computed_area_sqm'], 20);
        $this->assertEqualsCanonicalizing([60.0, 40.0], [$p['owner_1_share'], $p['owner_2_share']]);
        $this->assertSame('شارع 20م', $p['n_border']);
        $this->assertSame('Q-9', $p['survey_1_qrar_no']);
        $this->assertSame('صك.pdf', $p['document_1_name']);
        $this->assertStringContainsString('/documents/', $p['document_1_url']);

        // One value per column: nothing nested anywhere.
        $this->assertSame([], array_filter($p, 'is_array'));
    }

    public function test_every_feature_carries_the_same_columns(): void
    {
        $user = $this->exporter();
        $this->actingAs($user);

        $building = Parcel::where('geo_id', 'GEO-1')->first();
        Parcel::create(['geo_id' => 'FLAT-1', 'parcel_no' => '101-1', 'parent_parcel_id' => $building->id]);

        $features = $this->export([], $user)['features'];
        $columns = array_keys($features[0]['properties']);

        // Two owners on the fullest deed: two numbered owner sets on every row,
        // the deedless flat's included.
        $this->assertContains('owner_2_national_id', $columns);
        $this->assertNotContains('owner_3_name', $columns);
        foreach ($features as $feature) {
            $this->assertSame($columns, array_keys($feature['properties']));
        }
    }

    public function test_the_header_names_the_format_for_a_later_import(): void
    {
        $user = $this->exporter();
        $this->actingAs($user);

        $file = $this->export([], $user);

        $this->assertSame('FeatureCollection', $file['type']);
        $this->assertSame(DeedGeoJsonExporter::FORMAT, $file['sokuki']['format']);
        $this->assertSame(DeedGeoJsonExporter::VERSION, $file['sokuki']['version']);
        $this->assertSame(2, $file['sokuki']['count']);
        $this->assertCount(2, $file['features']);
    }

    public function test_filters_narrow_the_deeds(): void
    {
        $user = $this->exporter();
        $count = fn (array $filters): int => (new DeedExportFilters($filters))->query($user)->count();

        $this->assertSame(2, $count([]));
        $this->assertSame(1, $count(['city_id' => (string) $this->riyadh->id]));
        $this->assertSame(1, $count(['deed_status' => ['محدث']]));
        $this->assertSame(1, $count(['owner' => '1000000002']));
        $this->assertSame(1, $count(['has_geometry' => 'no']));
        $this->assertSame(1, $count(['asset_type' => ['أرض'], 'area_min' => '500', 'date_from' => '1445-01-01']));
        $this->assertSame(0, $count(['price_min' => '2000']));

        $this->deed->delete();
        $this->assertSame(1, $count([]));
        $this->assertSame(2, $count(['include_archived' => true]));
    }

    public function test_optional_parts_can_be_left_out(): void
    {
        $user = $this->exporter();
        $this->actingAs($user);

        $p = $this->export(['deed_no' => '310101'], $user, ['owners'])['features'][0];

        $this->assertNull($p['geometry']);
        $this->assertArrayHasKey('owner_1_name', $p['properties']);
        $this->assertArrayNotHasKey('n_border', $p['properties']);
        $this->assertArrayNotHasKey('document_1_name', $p['properties']);
        $this->assertArrayNotHasKey('survey_1_qrar_no', $p['properties']);
    }

    public function test_the_page_runs_an_export_and_only_its_owner_downloads_it(): void
    {
        $user = $this->exporter();
        $this->withoutDefer();

        $component = Livewire::actingAs($user)->test(ExportCenter::class)
            ->set('filters.deed_no', '310101')
            ->call('export');

        $id = (string) $component->get('following');
        $this->runs[] = $id;

        $this->assertSame('succeeded', ExportRuns::find($id)['state']);
        $this->actingAs($user)->get(route('exports.download', $id))->assertOk();
        $this->actingAs($this->exporter())->get(route('exports.download', $id))->assertForbidden();
    }

    public function test_parcels_without_deeds_units_and_history_are_exported(): void
    {
        $user = $this->exporter();
        $this->actingAs($user);

        $building = Parcel::where('geo_id', 'GEO-1')->first();
        Parcel::create(['geo_id' => 'FLAT-1', 'parcel_no' => '101-1', 'parent_parcel_id' => $building->id]);

        $file = $this->export([], $user);
        $flat = collect($file['features'])->firstWhere('properties.parcel_geo_id', 'FLAT-1');

        // Two deeds plus the flat, which has no deed.
        $this->assertCount(3, $file['features']);
        $this->assertSame('parcel-'.Parcel::where('geo_id', 'FLAT-1')->value('id'), $flat['id']);
        $this->assertNull($flat['properties']['deed_no']);
        $this->assertNull($flat['properties']['owner_1_name']);
        $this->assertSame('GEO-1', $flat['properties']['parent_geo_id']);

        $deed = collect($file['features'])->firstWhere('properties.deed_no', '310101000001')['properties'];
        $this->assertNotNull($deed['deed_created_at']);
        $this->assertNotNull($deed['parcel_created_at']);
        foreach (['mime_type', 'size_bytes', 'uploaded_by', 'uploaded_at', 'reviewed_by', 'reviewed_at', 'rejection_reason'] as $column) {
            $this->assertArrayHasKey('document_1_'.$column, $deed);
        }

        // A filter on a deed's own fields leaves deedless parcels out.
        $this->assertSame(1, (new DeedExportFilters(['deed_no' => '310101']))->count($user));
        $this->assertSame(3, (new DeedExportFilters([]))->count($user));
        $this->assertSame(2, (new DeedExportFilters(['include_deedless' => false]))->count($user));
    }

    public function test_owners_without_deeds_export_as_a_spreadsheet(): void
    {
        Owner::create(['name' => 'مالك بلا صك', 'national_id' => '1999999999']);

        $this->actingAs($this->exporter())
            ->get(route('exports.owners-without-deeds'))
            ->assertOk()
            ->assertDownload('owners-without-deeds-'.now()->format('Y-m-d').'.xlsx');

        $this->assertSame(1, OwnersWithoutDeedsExport::base(false)->count());
    }

    public function test_the_page_needs_the_permission(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('exports.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $groups
     * @return array<string, mixed>
     */
    private function export(array $filters, User $user, ?array $groups = null): array
    {
        $id = ExportRuns::newId();
        $this->runs[] = $id;

        $run = app(DeedGeoJsonExporter::class)->run($id, new DeedExportFilters($filters), $groups ?? DeedGeoJsonExporter::GROUPS, $user);
        $this->assertSame('succeeded', $run['state'], (string) $run['error']);

        return json_decode((string) file_get_contents(ExportRuns::filePath($id)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function exporter(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['exports.bulk', 'documents.download'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['exports.bulk', 'documents.download']);

        return $user;
    }
}
