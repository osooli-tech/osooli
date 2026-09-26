<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Livewire\Imports\MapLayerManager;
use App\Models\MapLayer;
use App\Models\User;
use App\Services\Import\GdbImporter;
use App\Support\Geo\LayerNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

/**
 * Custom map layers: a geodatabase layer that is not parcels, projects or
 * buildings kept whole and shown on the map, and the warning when its name
 * is close to a layer already there.
 */
class CustomLayersTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function tearDown(): void
    {
        if (isset($this->tmp)) {
            (new Process(PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'rmdir', '/s', '/q', $this->tmp] : ['rm', '-rf', $this->tmp]))->run();
        }
        parent::tearDown();
    }

    public function test_layer_names_are_compared_the_way_people_misspell_them(): void
    {
        $this->assertSame(1.0, LayerNames::similarity('Buildings', 'Building'));
        $this->assertSame(1.0, LayerNames::similarity('المباني', 'مباني'));
        $this->assertSame(1.0, LayerNames::similarity('Water_Wells', 'water wells'));
        $this->assertSame(1.0, LayerNames::similarity('آبار', 'الابار'));
        $this->assertGreaterThanOrEqual(LayerNames::SIMILAR, LayerNames::similarity('طرق', 'الطرق الزراعية'));
        $this->assertLessThan(LayerNames::SIMILAR, LayerNames::similarity('Fence', 'Farms'));
        $this->assertSame('buildings', LayerNames::builtInRole('المباني'));
        $this->assertNull(LayerNames::builtInRole('Adjacent_Parcel'));
    }

    public function test_a_new_layer_is_kept_whole_and_a_re_import_replaces_it(): void
    {
        $this->requireGdal();
        $importer = app(GdbImporter::class);

        $first = $this->zip('Water_Wells', [[46.60, 24.80, 'بئر 1', 30], [46.61, 24.81, 'بئر 2', 45]]);
        $options = $importer->analyze($first)->toArray()['details']['gdb']['suggested'];

        $this->assertSame(['name' => 'Water_Wells', 'role' => 'custom', 'target' => null, 'new_name' => 'Water_Wells', 'mode' => 'replace'], $options['layers'][0]);

        $result = $importer->commit($first, $options + ['source_name' => 'wells.zip'])->toArray();
        $layer = MapLayer::sole();

        $this->assertSame(['Water_Wells' => 2], $result['details']['custom_layers']);
        $this->assertSame('Water_Wells', $layer->name);
        $this->assertSame(2, $layer->feature_count);
        $this->assertStringContainsString('Point', (string) $layer->geometry_type);
        $this->assertContains('Depth', array_column($layer->fields ?? [], 'name'));
        $this->assertSame('wells.zip', $layer->source);

        // The same layer, spelled another way, three wells now: it is
        // recognised, suggested as that layer, and replaced — not duplicated.
        $second = $this->zip('water wells', [[46.60, 24.80, 'بئر 1', 30], [46.61, 24.81, 'بئر 2', 45], [46.62, 24.82, 'بئر 3', 12]]);
        $options = $importer->analyze($second)->toArray()['details']['gdb']['suggested'];
        $this->assertSame($layer->id, $options['layers'][0]['target']);

        $importer->commit($second, $options);
        $this->assertSame(1, MapLayer::count());
        $this->assertSame(3, $layer->fresh()->feature_count);
        $this->assertSame(3, DB::table('map_layer_features')->count());
    }

    public function test_a_merely_similar_name_is_warned_about_and_made_new_unless_chosen_otherwise(): void
    {
        $this->requireGdal();
        $existing = MapLayer::create(['name' => 'Wells']);

        $zip = $this->zip('Wells_2026', [[46.60, 24.80, 'بئر', 10]]);
        $preview = app(GdbImporter::class)->analyze($zip)->toArray()['details']['gdb'];

        $this->assertNull($preview['suggested']['layers'][0]['target']);
        $this->assertSame($existing->id, $preview['similar'][0][0]['id']);
        $this->assertSame('custom', $preview['similar'][0][0]['kind']);
    }

    public function test_the_map_serves_a_layer_with_every_attribute(): void
    {
        $this->requireGdal();
        $importer = app(GdbImporter::class);
        $zip = $this->zip('Water_Wells', [[46.60, 24.80, 'بئر 1', 30]]);
        $importer->commit($zip, $importer->analyze($zip)->toArray()['details']['gdb']['suggested']);
        $layer = MapLayer::sole();

        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->getJson(route('geo.layers.show', $layer))
            ->assertOk()
            ->assertJsonPath('features.0.geometry.type', 'Point')
            ->assertJsonPath('features.0.properties.Name', 'بئر 1')
            ->assertJsonPath('features.0.properties.Depth', 30);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-custom-layer="'.$layer->id.'"', false);
    }

    public function test_layers_are_renamed_recoloured_and_deleted_by_those_who_import(): void
    {
        $layer = MapLayer::create(['name' => 'Roads']);
        MapLayer::create(['name' => 'Wells']);
        DB::table('map_layer_features')->insert(['map_layer_id' => $layer->id, 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs(User::factory()->create(['is_active' => true]));
        Livewire::test(MapLayerManager::class)->assertForbidden();

        $admin = User::factory()->create(['is_active' => true]);
        Permission::firstOrCreate(['name' => 'imports.create', 'guard_name' => 'web']);
        $admin->givePermissionTo('imports.create');
        $this->actingAs($admin);

        Livewire::test(MapLayerManager::class)
            ->call('edit', $layer->id)
            ->set('name', 'Wells')
            ->call('save')
            ->assertHasErrors('name')
            ->set('name', 'Well')
            ->assertSee('Wells')
            ->set('color', '#123abc')
            ->set('visible', true)
            ->call('save')
            ->assertHasNoErrors();

        $layer->refresh();
        $this->assertSame(['Well', '#123abc', true], [$layer->name, $layer->color, $layer->visible_by_default]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'map_layer.update', 'target_id' => $layer->id]);

        Livewire::test(MapLayerManager::class)->call('delete', $layer->id);
        $this->assertDatabaseMissing('map_layers', ['id' => $layer->id]);
        $this->assertSame(0, DB::table('map_layer_features')->count());
    }

    private function requireGdal(): void
    {
        $probe = new Process([(string) config('imports.ogr2ogr_path'), '--version']);
        $probe->run();
        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('ogr2ogr (GDAL) is not installed on this machine.');
        }
    }

    /**
     * A zipped geodatabase holding one point layer.
     *
     * @param  list<array{0: float, 1: float, 2: string, 3: int}>  $points  lng, lat, name, depth
     */
    private function zip(string $layerName, array $points): string
    {
        $this->tmp ??= sys_get_temp_dir().'/custom-layers-'.Str::random(8);
        $dir = $this->tmp.'/'.Str::random(6);
        mkdir($dir.'/batch', 0775, true);

        file_put_contents($dir.'/layer.geojson', json_encode(['type' => 'FeatureCollection', 'features' => array_map(
            static fn (array $p): array => ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [$p[0], $p[1]]],
                'properties' => ['Name' => $p[2], 'Depth' => $p[3]]],
            $points
        )], JSON_UNESCAPED_UNICODE));

        $gdb = $dir.'/Layers.gdb';
        $process = new Process([(string) config('imports.ogr2ogr_path'), '-f', 'OpenFileGDB', $gdb, $dir.'/layer.geojson', '-nln', $layerName]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        $zipPath = $dir.'/batch/source.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (scandir($gdb) ?: [] as $file) {
            if (is_file($gdb.'/'.$file)) {
                $zip->addFile($gdb.'/'.$file, 'Layers.gdb/'.$file);
            }
        }
        $zip->close();

        return $zipPath;
    }
}
