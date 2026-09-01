<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\ArchiveException;
use App\Services\Import\GdbConverter;
use Tests\TestCase;
use ZipArchive;

final class GdbConverterTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/gdb_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    public function test_it_reports_a_missing_ogr2ogr_binary(): void
    {
        config(['imports.ogr2ogr_path' => '/nonexistent/ogr2ogr']);

        $this->assertFalse(app(GdbConverter::class)->isAvailable());
    }

    public function test_it_fails_with_a_message_naming_the_binary(): void
    {
        config(['imports.ogr2ogr_path' => '/nonexistent/ogr2ogr']);

        $zip = $this->tmp.'/x.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addFromString('Sakuki.gdb/gdb', 'x');
        $archive->close();

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/ogr2ogr/');

        app(GdbConverter::class)->convert($zip, $this->tmp.'/work');
    }

    public function test_it_fails_when_the_archive_holds_no_geodatabase(): void
    {
        $zip = $this->tmp.'/plain.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addFromString('notes.txt', 'nothing here');
        $archive->close();

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/\.gdb/');

        app(GdbConverter::class)->convert($zip, $this->tmp.'/work');
    }

    public function test_it_passes_a_geojson_file_straight_through(): void
    {
        $geojson = $this->tmp.'/data.geojson';
        file_put_contents($geojson, '{"type":"FeatureCollection","features":[]}');

        $this->assertSame($geojson, app(GdbConverter::class)->convert($geojson, $this->tmp.'/work'));
    }

    public function test_it_converts_a_real_geodatabase(): void
    {
        $converter = app(GdbConverter::class);

        if (! $converter->isAvailable()) {
            $this->markTestSkipped('ogr2ogr is not installed on this machine.');
        }

        $fixture = base_path('tests/fixtures/import/Sakuki.gdb.zip');

        if (! is_file($fixture)) {
            $this->markTestSkipped('The geodatabase fixture is not present.');
        }

        $out = $converter->convert($fixture, $this->tmp.'/work');

        $decoded = json_decode((string) file_get_contents($out), true);

        $this->assertSame('FeatureCollection', $decoded['type']);
        $this->assertNotEmpty($decoded['features']);
        $this->assertArrayHasKey('Geo_ID', $decoded['features'][0]['properties']);
    }
}
