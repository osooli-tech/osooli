<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Livewire\Imports\ImportWizard;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Import\GdbImporter;
use App\Support\Database\Spatial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

/**
 * The geodatabase review: what analyze() reports about every layer, and how
 * the choices made on the screen shape what commit() writes.
 *
 * Each test builds a real two-layer File Geodatabase with ogr2ogr, so they
 * skip where GDAL is not installed (point IMPORT_OGR2OGR_PATH and
 * IMPORT_OGRINFO_PATH at it to run them).
 */
class GdbReviewTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    private string $zip;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        $probe = new Process([(string) config('imports.ogr2ogr_path'), '--version']);
        $probe->run();
        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('ogr2ogr (GDAL) is not installed on this machine.');
        }

        $this->tmp = sys_get_temp_dir().'/gdb-review-'.Str::random(8);
        mkdir($this->tmp.'/batch', 0775, true);
        $this->zip = $this->buildGeodatabase();

        $country = DB::table('countries')->insertGetId(['name_ar' => 'السعودية', 'created_at' => now(), 'updated_at' => now()]);
        $region = DB::table('regions')->insertGetId(['name_ar' => 'منطقة الرياض', 'country_id' => $country, 'created_at' => now(), 'updated_at' => now()]);
        $this->cityId = DB::table('cities')->insertGetId(['name_ar' => 'ثادق', 'region_id' => $region, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tmp)) {
            (new Process(PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'rmdir', '/s', '/q', $this->tmp] : ['rm', '-rf', $this->tmp]))->run();
        }
        parent::tearDown();
    }

    public function test_analyze_describes_every_layer_and_writes_nothing(): void
    {
        $preview = app(GdbImporter::class)->analyze($this->zip)->toArray();
        $gdb = $preview['details']['gdb'];

        $this->assertSame(3, $preview['total_items']);
        $this->assertSame(['Parcel' => 'parcels', 'Building' => 'buildings'], array_column($gdb['layers'], 'role', 'name'));
        $this->assertSame([], $gdb['attachments']);
        $this->assertSame(3, collect($gdb['layers'][0]['fields'])->firstWhere('name', 'Geo_ID')['filled']);

        // Text labels are understood; an unknown one is flagged, not guessed.
        $fallIn = collect($gdb['analysis']['enums']['Fall_In'])->keyBy('value');
        $this->assertSame('طلبات احكام', $fallIn['طلبات احكام']['label']);
        $this->assertNull($fallIn['قيمة غريبة']['label']);

        $this->assertSame('number', $gdb['suggested']['qrar']);
        $this->assertSame('ignore', $gdb['suggested']['folder']);
        $this->assertSame(1, $gdb['analysis']['deeds_without_number']);
        $this->assertSame(0, DB::table('parcels')->count());
    }

    public function test_districts_are_matched_by_where_the_parcels_lie(): void
    {
        // On record under another spelling, with a boundary round the parcels.
        $square = json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[46.5, 24.7], [46.7, 24.7], [46.7, 24.9], [46.5, 24.9], [46.5, 24.7]]]]]);
        $district = DB::table('districts')->insertGetId(['name_ar' => 'اوثال الشمالي', 'city_id' => $this->cityId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['districts', $district], ['cities', $this->cityId]] as [$table, $id]) {
            DB::update("UPDATE {$table} SET geom = ".Spatial::fromGeoJson().", boundary_source = 'official' WHERE id = ?", [$square, $id]);
        }

        $suggested = app(GdbImporter::class)->analyze($this->zip)->toArray()['details']['gdb']['suggested'];
        $row = collect($suggested['districts'])->firstWhere('name', 'أوثال');

        $this->assertSame($district, $row['district_id']);
        $this->assertSame('map', $row['method']);
        $this->assertSame(100, $row['map_share']);
        $this->assertSame($this->cityId, $suggested['default_city_id']);
    }

    public function test_districts_can_be_chosen_by_location_by_row_and_per_parcel(): void
    {
        // A district on record whose boundary holds G-1 and G-2 but not G-3.
        $mapped = DB::table('districts')->insertGetId(['name_ar' => 'حي الخريطة', 'city_id' => $this->cityId, 'created_at' => now(), 'updated_at' => now()]);
        $square = json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[[46.595, 24.79], [46.615, 24.79], [46.615, 24.81], [46.595, 24.81], [46.595, 24.79]]]]]);
        DB::update('UPDATE districts SET geom = '.Spatial::fromGeoJson().", boundary_source = 'official' WHERE id = ?", [$square, $mapped]);
        $chosen = DB::table('districts')->insertGetId(['name_ar' => 'حي مختار', 'city_id' => $this->cityId, 'created_at' => now(), 'updated_at' => now()]);

        $importer = app(GdbImporter::class);
        $options = $importer->analyze($this->zip)->toArray()['details']['gdb']['suggested'];
        $options['default_city_id'] = $this->cityId;
        foreach ($options['districts'] as $i => $row) {
            $options['districts'][$i]['district_id'] = null;
        }
        $district = fn (string $geo): ?string => DB::table('parcels as p')->join('plans as pl', 'pl.id', '=', 'p.plan_id')
            ->join('districts as d', 'd.id', '=', 'pl.district_id')->where('p.geo_id', $geo)->value('d.name_ar');

        // By location for all, with G-2 given a district of its own.
        $options['district_match'] = 'map';
        $options['parcel_districts'] = [['geo_id' => 'G-2', 'district_id' => $chosen]];
        $result = $importer->commit($this->zip, $options)->toArray();

        $this->assertSame('حي الخريطة', $district('G-1'));
        $this->assertSame('حي مختار', $district('G-2'));
        $this->assertSame('أوثال', $district('G-3'), 'outside every boundary: matched by name');
        $this->assertSame(0, $result['errors']);
        // «بدون» parcels keep their district through its stand-in plan.
        $this->assertSame(1, DB::table('plans')->where('plan_no', 'بدون — أوثال — ثادق')->count());

        // The row set back to "by name" overrides the method for all.
        $options['districts'][0]['match'] = 'name';
        $options['parcel_districts'] = [];
        $importer->commit($this->zip, $options);
        $this->assertSame('أوثال', $district('G-1'));
    }

    public function test_commit_applies_the_choices(): void
    {
        // On record already: a value the file leaves empty must survive.
        DB::table('parcels')->insert(['geo_id' => 'G-1', 'parcel_no' => 'قديم', 'm_price' => 999, 'created_at' => now(), 'updated_at' => now()]);

        $importer = app(GdbImporter::class);
        $options = $importer->analyze($this->zip)->toArray()['details']['gdb']['suggested'];
        $options['default_city_id'] = $this->cityId;
        $options['borders'] = 'second';
        $options['deedless'] = 'skip';

        $result = $importer->commit($this->zip, $options)->toArray();

        $this->assertSame(0, $result['errors']);
        $this->assertSame(2, $result['details']['buildings']);
        $this->assertStringContainsString('قيمة غريبة', implode(' ', $result['warnings']));

        $g1 = DB::table('parcels')->where('geo_id', 'G-1')->first();
        $this->assertSame('طلبات احكام', $g1->fall_in);
        $this->assertEquals(999, $g1->m_price, 'an empty M_price leaves the value on record');
        // «بدون» is no real plan: G-1 sits in its district's stand-in plan.
        $this->assertSame('بدون — أوثال — ثادق', DB::table('plans')->where('id', $g1->plan_id)->value('plan_no'));
        $this->assertSame(0, DB::table('plans')->where('plan_no', 'بدون')->count());

        $this->assertSame('قديم', DB::table('deeds')->where('deed_no', 'D-1')->value('deed_status'));
        $this->assertSame(0, DB::table('deeds')->whereNull('deed_no')->count(), 'deedless skipped');
        $this->assertSame('77554', DB::table('survey_decisions')->where('parcel_id', $g1->id)->value('qrar_no'));
        $this->assertSame('شارع 2', DB::table('parcel_boundaries')->where('parcel_id', $g1->id)->value('n_border'));
        $this->assertSame(1, DB::table('owner_portfolios')->where('name', 'محفظة أ')->count());
        $this->assertSame('ثادق', DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')->where('d.name_ar', 'أوثال')->value('c.name_ar'));

        // Run again: parcels and deeds are updated, buildings replaced — not doubled.
        $importer->commit($this->zip, $options);
        $this->assertSame(3, DB::table('parcels')->count());
        $this->assertSame(2, DB::table('buildings')->count());
    }

    public function test_the_wizard_keeps_only_valid_choices_and_reopens_only_ones_own_batch(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'imports.create', 'guard_name' => 'web']);
        $user->givePermissionTo('imports.create');

        $preview = app(GdbImporter::class)->analyze($this->zip)->toArray();
        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'kind' => ImportKind::Gdb,
            'status' => ImportStatus::Previewed, 'original_filename' => 'f.zip', 'byte_size' => 1,
            'stored_path' => $this->zip, 'preview' => $preview,
        ]);
        $other = ImportBatch::create([
            'uuid' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'kind' => ImportKind::Gdb,
            'status' => ImportStatus::Previewed, 'original_filename' => 'g.zip', 'byte_size' => 1, 'preview' => $preview,
        ]);

        $component = Livewire::actingAs($user)->test(ImportWizard::class)
            ->call('open', $batch->uuid)
            ->assertSet('batchUuid', $batch->uuid)
            ->assertSee('Building')
            ->set('options.layers', [['name' => 'Parcel', 'role' => 'parcels'], ['name' => 'Invented', 'role' => 'parcels'], ['name' => 'Building', 'role' => 'nonsense']])
            ->set('options.default_city_id', (string) $this->cityId);

        config(['imports.queue_sync' => false]);
        $component->call('confirm');

        $options = $batch->fresh()->options;
        $this->assertSame(
            [['name' => 'Parcel', 'role' => 'parcels'], ['name' => 'Building', 'role' => 'ignore']],
            array_map(static fn (array $l): array => ['name' => $l['name'], 'role' => $l['role']], $options['layers'])
        );
        $this->assertSame($this->cityId, $options['default_city_id']);

        Livewire::actingAs($user)->test(ImportWizard::class)->call('open', $other->uuid)->assertNotFound();
    }

    /** A zipped geodatabase: a Parcel layer of three features and a Building layer of two. */
    private function buildGeodatabase(): string
    {
        $square = static fn (float $x): array => ['type' => 'MultiPolygon', 'coordinates' => [[[[$x, 24.8], [$x + 0.001, 24.8], [$x + 0.001, 24.801], [$x, 24.801], [$x, 24.8]]]]];
        $parcel = static fn (string $geo, float $x, array $p): array => ['type' => 'Feature', 'geometry' => $square($x), 'properties' => $p + [
            'Geo_ID' => $geo, 'Name' => 'مالك', 'Woner_ID' => '1000000001', 'Parcel' => null, 'Plan_No' => 'بدون',
            'Deed_No' => null, 'Deed_Status' => 'قديم', 'Deed_Class' => 'زراعي', 'Fall_In' => 'طلبات احكام', 'Owner_Type' => 'أرض',
            'District' => 'أوثال', 'Qrar' => null, 'Folder' => null, 'M_price' => null, 'Survey_Area' => 1000.5,
            'N_Border' => null, 'N_Border_2' => null, 'Real_Estate_portfolio' => 'محفظة أ',
        ]];

        $parcels = ['type' => 'FeatureCollection', 'features' => [
            $parcel('G-1', 46.6, ['Deed_No' => 'D-1', 'Qrar' => '77554', 'Folder' => 'المساحة بناء على الصك الأصلي', 'N_Border' => 'شارع 1', 'N_Border_2' => 'شارع 2']),
            $parcel('G-2', 46.61, ['Deed_No' => 'D-2', 'Plan_No' => '1165']),
            $parcel('G-3', 46.62, ['Fall_In' => 'قيمة غريبة']),
        ]];
        $buildings = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'geometry' => $square(46.63), 'properties' => ['Name' => 'سكن', 'Code' => 'Building', 'SHAPE_Area' => 120.0, 'SHAPE_Length' => 44.0]],
            ['type' => 'Feature', 'geometry' => $square(46.64), 'properties' => ['Name' => null, 'Code' => 'Building', 'SHAPE_Area' => 80.0, 'SHAPE_Length' => 36.0]],
        ]];

        file_put_contents($this->tmp.'/parcels.geojson', json_encode($parcels, JSON_UNESCAPED_UNICODE));
        file_put_contents($this->tmp.'/buildings.geojson', json_encode($buildings, JSON_UNESCAPED_UNICODE));

        $gdb = $this->tmp.'/Test.gdb';
        $bin = (string) config('imports.ogr2ogr_path');
        foreach ([
            [$bin, '-f', 'OpenFileGDB', $gdb, $this->tmp.'/parcels.geojson', '-nln', 'Parcel'],
            [$bin, '-f', 'OpenFileGDB', '-update', $gdb, $this->tmp.'/buildings.geojson', '-nln', 'Building'],
        ] as $command) {
            $process = new Process($command);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $zipPath = $this->tmp.'/batch/source.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (scandir($gdb) ?: [] as $file) {
            if (is_file($gdb.'/'.$file)) {
                $zip->addFile($gdb.'/'.$file, 'Test.gdb/'.$file);
            }
        }
        $zip->close();

        return $zipPath;
    }
}
