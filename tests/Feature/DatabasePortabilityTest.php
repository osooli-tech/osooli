<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Database\DatabaseSettings;
use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The SQL the application writes for each dialect, and the settings file
 * that chooses between them. None of this opens a database connection:
 * building a connection object is lazy, and the SQL is only inspected.
 */
class DatabasePortabilityTest extends TestCase
{
    private string $settingsFile;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsFile = sys_get_temp_dir().'/db-settings-'.bin2hex(random_bytes(4)).'.enc';
        $this->originalDefault = (string) config('database.default');

        // As if .env named PostgreSQL, whichever database the suite runs on.
        config(['database.settings_file' => $this->settingsFile, 'database.default' => 'pgsql']);
    }

    protected function tearDown(): void
    {
        @unlink($this->settingsFile);
        config(['database.default' => $this->originalDefault]);
        DB::setDefaultConnection($this->originalDefault);

        parent::tearDown();
    }

    public function test_spatial_sql_uses_postgis_on_postgres(): void
    {
        DB::setDefaultConnection('pgsql');

        $this->assertSame('ST_Area((parcels.geom)::geography)', Spatial::areaSqm('parcels.geom'));
        $this->assertSame('ST_Intersects(n.geom, ST_Expand(self.geom, 0.004000))', Spatial::intersectsExpanded('n.geom', 'self.geom', 0.004));
        $this->assertSame('a && b', Spatial::boxesIntersect('a', 'b'));
        $this->assertStringContainsString('ST_Multi(ST_GeomFromGeoJSON(?))', Spatial::fromGeoJson());
    }

    public function test_spatial_sql_avoids_postgis_only_functions_on_mariadb(): void
    {
        DB::setDefaultConnection('mariadb');

        $sql = implode(' ', [
            Spatial::areaSqm('parcels.geom'),
            Spatial::intersectsExpanded('n.geom', 'self.geom', 0.004),
            Spatial::boxesIntersect('a', 'b'),
            Spatial::fromGeoJson(),
            Spatial::extentSelect('geom'),
        ]);

        foreach (['::geography', 'ST_Expand', 'ST_Extent', 'ST_Multi', 'ST_SetSRID', '&&', 'ST_XMin'] as $postgisOnly) {
            $this->assertStringNotContainsString($postgisOnly, $sql);
        }
        $this->assertStringContainsString('MBRIntersects(a, b)', $sql);
    }

    public function test_multipolygon_json_wraps_a_polygon_and_refuses_other_types(): void
    {
        $polygon = ['type' => 'Polygon', 'coordinates' => [[[1, 1], [2, 1], [2, 2], [1, 1]]]];

        $this->assertSame(
            ['type' => 'MultiPolygon', 'coordinates' => [$polygon['coordinates']]],
            json_decode(Spatial::multiPolygonJson($polygon), true)
        );

        $this->expectException(InvalidArgumentException::class);
        Spatial::multiPolygonJson(['type' => 'Point', 'coordinates' => [1, 1]]);
    }

    public function test_without_a_settings_file_the_env_connection_is_used(): void
    {
        $settings = new DatabaseSettings(config());

        $settings->apply();

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', $settings->primary());
        $this->assertSame('mariadb', $settings->secondary());
        $this->assertFalse($settings->isConfigured('mariadb'));
    }

    public function test_saved_settings_are_encrypted_and_applied(): void
    {
        $settings = new DatabaseSettings(config());
        $settings->saveConnection('mariadb', [
            'host' => 'srv1.example.test', 'port' => '3306', 'database' => 'u1_app', 'username' => 'u1_user',
        ], 'secret-password');
        $settings->setPrimary('mariadb');

        $this->assertStringNotContainsString('secret-password', (string) file_get_contents($this->settingsFile));

        $fresh = new DatabaseSettings(config());
        $fresh->apply();

        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('srv1.example.test', config('database.connections.mariadb.host'));
        $this->assertSame('secret-password', config('database.connections.mariadb.password'));
        $this->assertNull(config('database.connections.mariadb.url'));
        // The connection .env used became an explicit entry when the primary changed.
        $this->assertNotNull($fresh->connection('pgsql'));
    }

    public function test_an_empty_password_keeps_the_saved_one(): void
    {
        $settings = new DatabaseSettings(config());
        $details = ['host' => 'h', 'port' => '3306', 'database' => 'd', 'username' => 'u'];

        $settings->saveConnection('mariadb', $details, 'first');
        $settings->saveConnection('mariadb', ['host' => 'h2'] + $details, null);

        $this->assertSame('first', $settings->connection('mariadb')['password']);
        $this->assertSame('h2', $settings->connection('mariadb')['host']);
    }

    public function test_an_unreadable_settings_file_falls_back_to_env(): void
    {
        file_put_contents($this->settingsFile, 'not-encrypted-data');

        $settings = new DatabaseSettings(config());
        $settings->apply();

        $this->assertTrue($settings->unreadableAfterRead());
        $this->assertSame('pgsql', config('database.default'));
    }
}
