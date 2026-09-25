<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Archive\ArchiveIndex;
use App\Models\ArchivedValue;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\SokukiDataCleanupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SokukiDataCleanupSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    private Parcel $parcel;

    private Deed $old;

    private Deed $current;

    private Owner $owner;

    private District $butayn;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'المملكة العربية السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة القصيم']);
        $buraidah = City::create(['region_id' => $region->id, 'name_ar' => 'بريدة']);
        $this->butayn = District::create(['city_id' => $buraidah->id, 'name_ar' => 'بطين-1']);
        District::create(['city_id' => $buraidah->id, 'name_ar' => 'وادي عنيزة']);
        $shared = Plan::create(['plan_no' => 'بدون', 'district_id' => $this->butayn->id]);

        $this->parcel = Parcel::create(['geo_id' => 'WADIUnaizah-PAR-04', 'plan_id' => $shared->id]);
        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((43.87 26.11, 43.88 26.12, 43.88 26.11, 43.87 26.12, 43.87 26.11)))', 4326) WHERE id = ?",
            [$this->parcel->id]
        );
        DB::table('parcel_boundaries')->insert(['parcel_id' => $this->parcel->id, 'e_border' => 'وادي ', 'created_at' => now(), 'updated_at' => now()]);

        $this->old = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '111', 'deed_date_hijri' => '0444-09-21']);
        $this->current = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '222']);
        $this->owner = Owner::create(['name' => 'أحمد  الدهش', 'national_id' => '47192', 'phone' => '0511111157']);

        $area = (float) DB::selectOne('SELECT ST_Area(geom::geography) AS a FROM parcels WHERE id = ?', [$this->parcel->id])->a;

        $this->file = tempnam(sys_get_temp_dir(), 'fixes');
        file_put_contents($this->file, json_encode([
            'districts' => [
                ['name_ar' => 'وادي عنيزة', 'name_en' => 'Wadi Unayzah', 'city_ar' => 'عنيزة', 'city_en' => 'Unayzah', 'city_national_address_id' => 80, 'region_ar' => 'منطقة القصيم'],
            ],
            'plans' => [
                ['parcel_id' => $this->parcel->id, 'geo_id' => 'WADIUnaizah-PAR-04', 'plan_no' => 'بدون - وادي عنيزة', 'district' => 'وادي عنيزة', 'reason' => 'r'],
            ],
            'values' => [
                ['table' => 'owners', 'id' => $this->owner->id, 'field' => 'phone', 'from' => '0511111157', 'to' => null, 'reason' => 'demo'],
                ['table' => 'owners', 'id' => $this->owner->id, 'field' => 'national_id', 'from' => '47192', 'to' => null, 'reason' => 'bad id'],
                ['table' => 'owners', 'id' => $this->owner->id, 'field' => 'name', 'from' => 'أحمد  الدهش', 'to' => 'أحمد الدهش', 'reason' => 'spaces'],
                ['table' => 'parcel_boundaries', 'id' => 0, 'parcel_id' => $this->parcel->id, 'field' => 'e_border', 'from' => 'وادي ', 'to' => 'وادي', 'reason' => 'spaces'],
                ['table' => 'deeds', 'id' => $this->old->id, 'field' => 'deed_date_hijri', 'from' => '0444-09-21', 'to' => '1444-09-21', 'reason' => 'year'],
            ],
            'geometries' => [
                ['parcel_id' => $this->parcel->id, 'geo_id' => 'WADIUnaizah-PAR-04', 'before_area' => $area, 'reason' => 'bow tie',
                    'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [[[[43.87, 26.11], [43.875, 26.115], [43.87, 26.12], [43.87, 26.11]]]]]],
            ],
            'archive' => [
                'deeds' => [['id' => $this->old->id, 'deed_no' => '111', 'geo_id' => 'WADIUnaizah-PAR-04', 'reason' => 'old']],
                'parcels' => [],
            ],
        ], JSON_UNESCAPED_UNICODE));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    private function seedFixes(): void
    {
        $seeder = new SokukiDataCleanupSeeder;
        $seeder->path = $this->file;
        $seeder->run();
    }

    public function test_it_moves_parcels_to_their_real_district_and_city(): void
    {
        $this->seedFixes();

        $district = District::where('name_ar', 'وادي عنيزة')->firstOrFail();
        $this->assertSame('عنيزة', $district->city->name_ar);
        $this->assertSame('Wadi Unayzah', $district->name_en);

        $plan = Plan::where('plan_no', 'بدون - وادي عنيزة')->firstOrFail();
        $this->assertSame($district->id, $plan->district_id);
        $this->assertSame($plan->id, $this->parcel->fresh()->plan_id);

        // The shared plan and its district are not touched.
        $this->assertSame($this->butayn->id, Plan::where('plan_no', 'بدون')->value('district_id'));
    }

    public function test_emptied_and_corrected_values_are_kept_in_the_archive(): void
    {
        $this->seedFixes();

        $owner = $this->owner->fresh();
        $this->assertNull($owner->phone);
        $this->assertNull($owner->phone_normalized);
        $this->assertNull($owner->national_id);
        $this->assertSame('أحمد الدهش', $owner->name);
        $this->assertSame('وادي', DB::table('parcel_boundaries')->where('parcel_id', $this->parcel->id)->value('e_border'));

        $this->assertDatabaseHas('archived_values', ['record_table' => 'owners', 'record_id' => $owner->id, 'field' => 'phone', 'value' => '0511111157']);
        $this->assertDatabaseHas('archived_values', ['record_table' => 'deeds', 'field' => 'deed_date_hijri', 'value' => '0444-09-21']);

        ArchivedValue::where('field', 'phone')->firstOrFail()->restore();

        $this->assertSame('0511111157', $owner->fresh()->phone);
        $this->assertSame('511111157', $owner->fresh()->phone_normalized);
        $this->assertDatabaseMissing('archived_values', ['field' => 'phone']);
    }

    public function test_old_deeds_are_archived_and_polygons_keep_their_history(): void
    {
        $this->seedFixes();

        $this->assertSoftDeleted('deeds', ['id' => $this->old->id]);
        $this->assertNotSoftDeleted('deeds', ['id' => $this->current->id]);

        $this->assertTrue((bool) DB::selectOne('SELECT ST_IsValid(geom) AS v FROM parcels WHERE id = ?', [$this->parcel->id])->v);
        $this->assertDatabaseHas('parcel_geometry_revisions', ['parcel_id' => $this->parcel->id, 'action' => 'cleanup']);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seedFixes();
        $counts = [DB::table('archived_values')->count(), DB::table('parcel_geometry_revisions')->count(), DB::table('plans')->count()];

        $this->seedFixes();

        $this->assertSame($counts, [DB::table('archived_values')->count(), DB::table('parcel_geometry_revisions')->count(), DB::table('plans')->count()]);
    }

    public function test_the_archive_screen_lists_and_restores_values(): void
    {
        $this->seedFixes();

        $user = User::factory()->create(['is_active' => true]);
        foreach (['archive.view', 'archive.restore'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['archive.view', 'archive.restore']);

        $value = ArchivedValue::where('field', 'national_id')->firstOrFail();

        Livewire::actingAs($user)->test(ArchiveIndex::class)
            ->call('switchTab', 'values')
            ->assertSee('47192')
            ->call('restore', $value->id);

        $this->assertSame('47192', $this->owner->fresh()->national_id);
    }
}
