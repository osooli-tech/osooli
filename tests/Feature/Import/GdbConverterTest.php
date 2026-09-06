<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\ArchiveException;
use App\Services\Import\GdbConverter;
use App\Services\Import\GdbLayerPicker;
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

        // Availability is checked before the archive is ever touched (Finding
        // 2), so the source path does not need to exist for this failure.
        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/ogr2ogr/');
        $this->expectExceptionMessageMatches('/IMPORT_OGR2OGR_PATH/');

        app(GdbConverter::class)->convert($this->tmp.'/whatever.zip', $this->tmp.'/work');
    }

    public function test_it_fails_when_the_archive_holds_no_geodatabase(): void
    {
        // Availability is now checked before extraction, so on this machine
        // (no real ogr2ogr) this test must point at a binary that reports
        // itself available without actually being ogr2ogr, or it would never
        // reach the .gdb check. `php --version` exits 0, which is all
        // isAvailable() looks at.
        config(['imports.ogr2ogr_path' => PHP_BINARY]);

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
            $this->markTestSkipped(
                'The geodatabase fixture is intentionally not committed to the repository — '
                .'it would be the client\'s real geodatabase (168 land records, including owner '
                .'names and national IDs). See docs/import-runbook.md §4 ("Step 1 — Import the '
                .'parcels GDB") for the intended manual end-to-end verification of this path.'
            );
        }

        $out = $converter->convert($fixture, $this->tmp.'/work');

        $decoded = json_decode((string) file_get_contents($out), true);

        $this->assertSame('FeatureCollection', $decoded['type']);
        $this->assertNotEmpty($decoded['features']);
        $this->assertArrayHasKey('Geo_ID', $decoded['features'][0]['properties']);
    }

    /**
     * Regression test for I4 in the final review: GdbImporter::analyze() and
     * commit() both call convert() with the same ($sourcePath, $workDir) for
     * one batch, and convert() used to re-extract and re-run ogr2ogr on
     * every single call with no -overwrite and nothing cleaning up. This
     * proves the fix — a second convert() call for the same $workDir must
     * not invoke ogr2ogr (or even check isAvailable(), which also shells
     * out) again — with a stub binary that appends a line to a counter file
     * on every invocation, so "did it run again" is a fact on disk rather
     * than something inferred from timing or output content.
     */
    public function test_a_second_convert_for_the_same_work_dir_does_not_re_invoke_ogr2ogr(): void
    {
        $counter = $this->tmp.'/ogr2ogr_calls.log';
        config(['imports.ogr2ogr_path' => $this->stubCountingOgr2Ogr($counter)]);

        $json = '{"layers":[{"name":"sakoki_with_deed","fields":[{"name":"Geo_ID"},{"name":"Deed_No"}]}]}';
        config(['imports.ogrinfo_path' => $this->stubOgrinfoJson($json)]);

        $zip = $this->tmp.'/gdb.zip';
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addEmptyDir('Fake.gdb');
        $archive->close();

        $converter = app(GdbConverter::class);
        $workDir = $this->tmp.'/work';

        $first = $converter->convert($zip, $workDir);
        $callsAfterFirst = $this->countLines($counter);

        $second = $converter->convert($zip, $workDir);
        $callsAfterSecond = $this->countLines($counter);

        $this->assertSame($first, $second);
        $this->assertFileExists($first);
        $this->assertGreaterThan(0, $callsAfterFirst, 'the first call must have invoked the stub at least once');
        $this->assertSame(
            $callsAfterFirst,
            $callsAfterSecond,
            'a second convert() for the same work dir must not invoke ogr2ogr again'
        );
    }

    private function countLines(string $path): int
    {
        return is_file($path) ? count(array_filter(explode("\n", (string) file_get_contents($path)))) : 0;
    }

    /** A stub ogr2ogr that logs every invocation to $counterFile before responding. */
    private function stubCountingOgr2Ogr(string $counterFile): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = $this->tmp.'/ogr2ogr_counting.bat';
            $counter = str_replace('/', '\\', $counterFile);
            $body = <<<BAT
@echo off
echo called>>"{$counter}"
if "%1"=="--version" (
  echo GDAL 3.0
  exit /b 0
)
echo stub>"%~6"
exit /b 0
BAT;
            file_put_contents($path, $body."\r\n");

            return $path;
        }

        $path = $this->tmp.'/ogr2ogr_counting.sh';
        $body = <<<SH
#!/bin/sh
echo called >> "{$counterFile}"
if [ "\$1" = "--version" ]; then
  echo "GDAL 3.0"
  exit 0
fi
echo stub > "\$6"
exit 0
SH;
        file_put_contents($path, $body."\n");
        chmod($path, 0755);

        return $path;
    }

    public function test_layer_picker_picks_the_layer_carrying_geo_id_and_deed_no(): void
    {
        // Two layers, as in the real fixture: Adjacent_Parcel (neither
        // field) must be skipped in favour of sakoki_with_deed (both).
        $json = '{"layers":[{"name":"Adjacent_Parcel","fields":[{"name":"OBJECTID"},{"name":"Shape_Area"}]},'
            .'{"name":"sakoki_with_deed","fields":[{"name":"OBJECTID"},{"name":"Geo_ID"},{"name":"Deed_No"}]}]}';

        config(['imports.ogrinfo_path' => $this->stubOgrinfoJson($json)]);

        $this->assertSame('sakoki_with_deed', (new GdbLayerPicker)->pick('irrelevant.gdb'));
    }

    public function test_layer_picker_falls_back_to_plain_text_output_on_older_gdal(): void
    {
        // Simulates a GDAL build older than 3.7, whose ogrinfo has no -json
        // support: the stub fails when called with -json and only succeeds
        // on the plain-text call, exercising the second parse path.
        config(['imports.ogrinfo_path' => $this->stubOgrinfoTextFallback()]);

        $this->assertSame('sakoki_with_deed', (new GdbLayerPicker)->pick('irrelevant.gdb'));
    }

    public function test_layer_picker_fails_loudly_instead_of_guessing_when_ogrinfo_cannot_run(): void
    {
        config(['imports.ogrinfo_path' => '/nonexistent/ogrinfo']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/ogrinfo/');

        (new GdbLayerPicker)->pick('irrelevant.gdb');
    }

    private function stubOgrinfoJson(string $json): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = $this->tmp.'/ogrinfo_json.bat';
            file_put_contents($path, "@echo off\r\necho {$json}\r\nexit /b 0\r\n");

            return $path;
        }

        $path = $this->tmp.'/ogrinfo_json.sh';
        file_put_contents($path, "#!/bin/sh\necho '{$json}'\nexit 0\n");
        chmod($path, 0755);

        return $path;
    }

    private function stubOgrinfoTextFallback(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = $this->tmp.'/ogrinfo_text.bat';
            $body = <<<'BAT'
@echo off
echo %* | findstr /C:"-json" >nul
if %errorlevel%==0 (
  exit /b 1
)
echo Layer name: Adjacent_Parcel
echo Geometry: Polygon
echo Feature Count: 1090
echo OBJECTID: Integer (0.0)
echo.
echo Layer name: sakoki_with_deed
echo Geometry: Polygon
echo Feature Count: 168
echo Geo_ID: String (50.0)
echo Deed_No: String (50.0)
exit /b 0
BAT;
            file_put_contents($path, $body."\r\n");

            return $path;
        }

        $path = $this->tmp.'/ogrinfo_text.sh';
        $body = <<<'SH'
#!/bin/sh
case "$*" in
  *-json*) exit 1 ;;
esac
cat <<'EOF'
Layer name: Adjacent_Parcel
Geometry: Polygon
Feature Count: 1090
OBJECTID: Integer (0.0)

Layer name: sakoki_with_deed
Geometry: Polygon
Feature Count: 168
Geo_ID: String (50.0)
Deed_No: String (50.0)
EOF
exit 0
SH;
        file_put_contents($path, $body."\n");
        chmod($path, 0755);

        return $path;
    }
}
