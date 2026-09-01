# Dashboard Data Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an administrator upload a zipped ESRI geodatabase or a ZIP of deed/survey PDFs from the dashboard, see exactly what the file contains, and write it to the database only after confirming.

**Architecture:** One `import_batches` row tracks each upload through a forward-only state machine. Two `Importer` implementations sit behind a common interface where `analyze()` reads and `commit()` writes; queued jobs drive them. The existing artisan commands are refactored to call the same services, so the CLI and the dashboard cannot drift apart.

**Tech Stack:** Laravel 11, Livewire 4, PostgreSQL 16 + PostGIS, Spatie Permission, PHPUnit 11, `ogr2ogr` (GDAL) as an external binary.

**Spec:** `docs/superpowers/specs/2026-09-01-dashboard-import-design.md`

## Global Constraints

- **PHP 8.2+**, `declare(strict_types=1);` at the top of every PHP file — the whole repo does this.
- **Every class is `final` unless something extends it.** Match surrounding style; existing models and commands are not final, so do not make models final.
- **Arabic is the primary UI language.** Every user-facing string goes through `__()` with entries in **both** `lang/ar/` and `lang/en/`. Never hardcode display text.
- **Hijri dates are plain text `YYYY-MM-DD`, never calendar-converted.** This is load-bearing: `deeds.deed_date_hijri` is a `string(10)`.
- **Tests run against real PostgreSQL**, database `sakuki_test` (see `phpunit.xml`). The schema needs PostGIS and custom enum types, so SQLite is not an option. `QUEUE_CONNECTION=sync` in tests.
- **Coded domains map 1-based numeric codes to position** in `ENUM_VALUES` (`"1"` → first value). Documented in `docs/gdb-coded-domains.md`. Do not "fix" this to string matching.
- **Livewire actions are client-callable** — authorize server-side in the action, never only with a blade `@can`.
- **PHPStan (larastan) level per `phpstan.neon` must stay green**; run `vendor/bin/phpstan analyse` before each commit.
- **Formatting:** `vendor/bin/pint` before each commit.
- **Chunk size 2 MB; max upload 512 MB; batch retention 7 days.**
- Accepted uploads: `.zip` or `.geojson` for `kind=gdb`; `.zip` only for `kind=documents`. **PHP has no `rar` extension — RAR is never supported.**

## Source data facts the tests depend on

From the supplied `Sakuki.gdb`, layer `sakoki_with_deed` (168 features, EPSG:32638):

- `Geo_ID` `28-112` and `34-82` each appear **twice with different deed numbers and different owners** — one parcel, two deeds. Not co-ownership.
- Deed `911605004832` appears on **two** `Geo_ID`s (`401-61`, `401-2`). Same for `996426000780` and `896426000682`.
- **No true co-ownership exists** — every `(Geo_ID, Deed_No)` pair is unique. The co-owner path must be tested with a synthesized fixture feature.
- National ID `1026382711` holds **17** parcels — the owner-dedup path is well exercised by real data.
- Plan `20A` exists — **plan numbers are not numeric**.
- Coded fields arrive as numeric strings: `Deed_Status="1"`, `Qrar="2"`, `Land_Trasaction="4"`.

---

### Task 1: ArchiveExtractor — safe unzip

Everything downstream extracts an untrusted ZIP. This lands first so nothing else has to think about path traversal.

**Files:**
- Create: `app/Services/Import/ArchiveExtractor.php`
- Create: `app/Services/Import/ArchiveException.php`
- Test: `tests/Unit/Import/ArchiveExtractorTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `ArchiveExtractor::extract(string $zipPath, string $destination): string` — returns `$destination`, throws `ArchiveException`. `ArchiveExtractor::__construct(int $maxEntries = 5000, int $maxTotalBytes = 2_147_483_648)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\ArchiveException;
use App\Services\Import\ArchiveExtractor;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ArchiveExtractorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/ax_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function makeZip(string $name, array $entries): string
    {
        $path = $this->tmp.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }
        $zip->close();

        return $path;
    }

    public function test_it_extracts_entries_to_the_destination(): void
    {
        $zip = $this->makeZip('ok.zip', ['a.txt' => 'hello', 'sub/b.txt' => 'world']);
        $dest = $this->tmp.'/out';

        (new ArchiveExtractor)->extract($zip, $dest);

        $this->assertSame('hello', file_get_contents($dest.'/a.txt'));
        $this->assertSame('world', file_get_contents($dest.'/sub/b.txt'));
    }

    public function test_it_rejects_an_entry_that_escapes_the_destination(): void
    {
        $zip = $this->makeZip('evil.zip', ['../escaped.txt' => 'pwned']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/outside/i');

        (new ArchiveExtractor)->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_an_absolute_entry_path(): void
    {
        $zip = $this->makeZip('abs.zip', ['/etc/passwd' => 'pwned']);

        $this->expectException(ArchiveException::class);

        (new ArchiveExtractor)->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_an_archive_with_too_many_entries(): void
    {
        $zip = $this->makeZip('many.zip', ['a.txt' => 'a', 'b.txt' => 'b', 'c.txt' => 'c']);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/entries/i');

        (new ArchiveExtractor(maxEntries: 2))->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_an_archive_whose_contents_exceed_the_size_cap(): void
    {
        $zip = $this->makeZip('big.zip', ['a.txt' => str_repeat('x', 5000)]);

        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessageMatches('/size/i');

        (new ArchiveExtractor(maxTotalBytes: 1000))->extract($zip, $this->tmp.'/out');
    }

    public function test_it_rejects_a_file_that_is_not_a_zip(): void
    {
        $path = $this->tmp.'/not.zip';
        file_put_contents($path, 'definitely not a zip');

        $this->expectException(ArchiveException::class);

        (new ArchiveExtractor)->extract($path, $this->tmp.'/out');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Import/ArchiveExtractorTest.php`
Expected: FAIL — `Class "App\Services\Import\ArchiveExtractor" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/Import/ArchiveException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;

final class ArchiveException extends RuntimeException {}
```

`app/Services/Import/ArchiveExtractor.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use ZipArchive;

/**
 * Extracts an untrusted ZIP to a destination directory.
 *
 * Uploads come from the browser, so every entry is treated as hostile:
 * paths are resolved against the destination and rejected if they escape it
 * (zip-slip), and both entry count and uncompressed size are capped so a zip
 * bomb cannot fill the disk.
 */
final class ArchiveExtractor
{
    public function __construct(
        private readonly int $maxEntries = 5000,
        private readonly int $maxTotalBytes = 2_147_483_648,
    ) {}

    /** @throws ArchiveException */
    public function extract(string $zipPath, string $destination): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new ArchiveException('The file could not be opened as a ZIP archive.');
        }

        if ($zip->numFiles > $this->maxEntries) {
            $zip->close();
            throw new ArchiveException("The archive holds more than {$this->maxEntries} entries.");
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                $zip->close();
                throw new ArchiveException("Entry #{$i} could not be read.");
            }

            $total += (int) $stat['size'];

            if ($total > $this->maxTotalBytes) {
                $zip->close();
                throw new ArchiveException('The archive’s uncompressed size exceeds the allowed limit.');
            }

            $this->assertSafePath((string) $stat['name']);
        }

        if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
            $zip->close();
            throw new ArchiveException("Could not create the destination directory: {$destination}");
        }

        if (! $zip->extractTo($destination)) {
            $zip->close();
            throw new ArchiveException('The archive could not be extracted.');
        }

        $zip->close();

        return $destination;
    }

    /**
     * Rejects absolute paths and any path that climbs out of the destination.
     * Checked on the entry name before extraction — realpath() is useless here
     * because the file does not exist yet.
     *
     * @throws ArchiveException
     */
    private function assertSafePath(string $name): void
    {
        $normalised = str_replace('\\', '/', $name);

        if (str_starts_with($normalised, '/') || preg_match('/^[A-Za-z]:/', $normalised) === 1) {
            throw new ArchiveException("Archive entry uses an absolute path and would land outside the destination: {$name}");
        }

        $depth = 0;
        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $depth += $segment === '..' ? -1 : 1;

            if ($depth < 0) {
                throw new ArchiveException("Archive entry would be written outside the destination: {$name}");
            }
        }
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Import/ArchiveExtractorTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Lint, analyse, commit**

```bash
vendor/bin/pint app/Services/Import tests/Unit/Import
vendor/bin/phpstan analyse
git add app/Services/Import tests/Unit/Import
git commit -m "feat(import): add safe archive extractor with zip-slip and size guards"
```

---

### Task 2: Enums, value objects, and the Importer interface

The shared vocabulary every later task speaks. No behaviour yet, so the tests are about shape and the state machine's rules.

**Files:**
- Create: `app/Enums/ImportKind.php`, `app/Enums/ImportStatus.php`
- Create: `app/Services/Import/ImportPreview.php`, `app/Services/Import/ImportResult.php`, `app/Services/Import/Importer.php`
- Test: `tests/Unit/Import/ImportStatusTest.php`, `tests/Unit/Import/ImportValueObjectsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `ImportKind: string` — cases `Gdb = 'gdb'`, `Documents = 'documents'`.
  - `ImportStatus: string` — `Uploading`, `Uploaded`, `Analyzing`, `Previewed`, `Committing`, `Completed`, `Failed`; method `canTransitionTo(self $next): bool`.
  - `ImportPreview::__construct(int $totalItems, int $willCreate, int $willUpdate, int $unmatched, array $details, array $warnings)` with `toArray(): array`.
  - `ImportResult::__construct(int $created, int $updated, int $skipped, int $errors, array $details, array $warnings)` with `toArray(): array`.
  - `Importer::analyze(string $sourcePath): ImportPreview` and `Importer::commit(string $sourcePath): ImportResult`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Import/ImportStatusTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Enums\ImportStatus;
use PHPUnit\Framework\TestCase;

final class ImportStatusTest extends TestCase
{
    public function test_it_allows_the_forward_path(): void
    {
        $this->assertTrue(ImportStatus::Uploading->canTransitionTo(ImportStatus::Uploaded));
        $this->assertTrue(ImportStatus::Uploaded->canTransitionTo(ImportStatus::Analyzing));
        $this->assertTrue(ImportStatus::Analyzing->canTransitionTo(ImportStatus::Previewed));
        $this->assertTrue(ImportStatus::Previewed->canTransitionTo(ImportStatus::Committing));
        $this->assertTrue(ImportStatus::Committing->canTransitionTo(ImportStatus::Completed));
    }

    public function test_any_live_state_can_fail(): void
    {
        foreach ([ImportStatus::Uploading, ImportStatus::Uploaded, ImportStatus::Analyzing, ImportStatus::Previewed, ImportStatus::Committing] as $status) {
            $this->assertTrue($status->canTransitionTo(ImportStatus::Failed), $status->value.' should be able to fail');
        }
    }

    public function test_it_refuses_to_skip_the_preview(): void
    {
        $this->assertFalse(ImportStatus::Uploaded->canTransitionTo(ImportStatus::Committing));
        $this->assertFalse(ImportStatus::Analyzing->canTransitionTo(ImportStatus::Committing));
    }

    public function test_it_refuses_to_move_backwards_or_recommit(): void
    {
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Committing));
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Previewed));
        $this->assertFalse(ImportStatus::Committing->canTransitionTo(ImportStatus::Committing));
    }

    public function test_terminal_states_go_nowhere(): void
    {
        $this->assertFalse(ImportStatus::Completed->canTransitionTo(ImportStatus::Failed));
        $this->assertFalse(ImportStatus::Failed->canTransitionTo(ImportStatus::Analyzing));
    }
}
```

`tests/Unit/Import/ImportValueObjectsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\ImportPreview;
use App\Services\Import\ImportResult;
use PHPUnit\Framework\TestCase;

final class ImportValueObjectsTest extends TestCase
{
    public function test_preview_serialises_every_counter(): void
    {
        $preview = new ImportPreview(
            totalItems: 168,
            willCreate: 12,
            willUpdate: 156,
            unmatched: 3,
            details: ['layer' => 'sakoki_with_deed'],
            warnings: ['plan 20A is not numeric'],
        );

        $this->assertSame([
            'total_items' => 168,
            'will_create' => 12,
            'will_update' => 156,
            'unmatched' => 3,
            'details' => ['layer' => 'sakoki_with_deed'],
            'warnings' => ['plan 20A is not numeric'],
        ], $preview->toArray());
    }

    public function test_result_serialises_every_counter(): void
    {
        $result = new ImportResult(
            created: 12,
            updated: 156,
            skipped: 3,
            errors: 1,
            details: ['deeds' => 165],
            warnings: [],
        );

        $this->assertSame([
            'created' => 12,
            'updated' => 156,
            'skipped' => 3,
            'errors' => 1,
            'details' => ['deeds' => 165],
            'warnings' => [],
        ], $result->toArray());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Import`
Expected: FAIL — `Class "App\Enums\ImportStatus" not found`.

- [ ] **Step 3: Write the implementation**

`app/Enums/ImportKind.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportKind: string
{
    case Gdb = 'gdb';
    case Documents = 'documents';
}
```

`app/Enums/ImportStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Forward-only import lifecycle.
 *
 * The rule that matters: only Previewed may move to Committing. That is what
 * stops a replayed request from writing twice and stops anything committing
 * without having been analysed first.
 */
enum ImportStatus: string
{
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case Previewed = 'previewed';
    case Committing = 'committing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** @return list<self> */
    private function allowedNext(): array
    {
        return match ($this) {
            self::Uploading => [self::Uploaded, self::Failed],
            self::Uploaded => [self::Analyzing, self::Failed],
            self::Analyzing => [self::Previewed, self::Failed],
            self::Previewed => [self::Committing, self::Failed],
            self::Committing => [self::Completed, self::Failed],
            self::Completed, self::Failed => [],
        };
    }
}
```

`app/Services/Import/ImportPreview.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

/** What an analyze() pass found. Never reflects a write. */
final readonly class ImportPreview
{
    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $totalItems,
        public int $willCreate,
        public int $willUpdate,
        public int $unmatched,
        public array $details = [],
        public array $warnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_items' => $this->totalItems,
            'will_create' => $this->willCreate,
            'will_update' => $this->willUpdate,
            'unmatched' => $this->unmatched,
            'details' => $this->details,
            'warnings' => $this->warnings,
        ];
    }
}
```

`app/Services/Import/ImportResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

/** What a commit() pass actually did. */
final readonly class ImportResult
{
    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public int $errors,
        public array $details = [],
        public array $warnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'details' => $this->details,
            'warnings' => $this->warnings,
        ];
    }
}
```

`app/Services/Import/Importer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

interface Importer
{
    /**
     * Inspect the source and report what a commit would do.
     *
     * Implementations MUST NOT write to the database. The whole
     * preview-then-confirm flow rests on this, and the test suite asserts it
     * by comparing row counts before and after.
     */
    public function analyze(string $sourcePath): ImportPreview;

    /** Apply the source to the database. Must be idempotent. */
    public function commit(string $sourcePath): ImportResult;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Import`
Expected: PASS.

- [ ] **Step 5: Lint, analyse, commit**

```bash
vendor/bin/pint app/Enums app/Services/Import tests/Unit/Import
vendor/bin/phpstan analyse
git add app/Enums app/Services/Import tests/Unit/Import
git commit -m "feat(import): add import enums, value objects and the Importer contract"
```

---

### Task 3: ParcelGeoJsonImporter — lift the logic out of the artisan command

The largest task. The existing command's import logic is correct and battle-tested; it moves to a service **verbatim** apart from three deliberate changes. Resist the urge to tidy it further — behaviour changes here are invisible until production data is wrong.

**Files:**
- Create: `app/Services/Import/ParcelGeoJsonImporter.php`
- Modify: `app/Console/Commands/ImportParcelsGeoJson.php` (reduce to a wrapper)
- Create: `tests/fixtures/import/parcels.geojson`
- Test: `tests/Feature/Import/ParcelGeoJsonImporterTest.php`

**Interfaces:**
- Consumes: `ImportPreview`, `ImportResult`, `Importer` (Task 2).
- Produces: `ParcelGeoJsonImporter implements Importer`, plus `ParcelGeoJsonImporter::importFeatures(array $features): ImportResult` and `ParcelGeoJsonImporter::previewFeatures(array $features): ImportPreview` for callers that already hold decoded GeoJSON (Task 5 uses these).

The three deliberate changes from the current command:

1. `$this->info()` / `$this->warn()` calls become entries in a collected `warnings` array — a service has no console.
2. `parcel_boundaries.measured_area` is populated from the feature's `Survey_Area` instead of the hardcoded `NULL`. In the current SQL the value list is `(?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)`; the `NULL` becomes a bound parameter.
3. A new `analyze()` that groups features and counts create-vs-update by looking up `geo_id`, **without writing**.

- [ ] **Step 1: Generate the test fixture**

Run this from the repo root. It reads the geodatabase the client supplied and writes a small GeoJSON fixture containing the awkward cases. Requires Python with `fiona` (`pip install fiona`).

```bash
mkdir -p tests/fixtures/import
python - <<'PY'
import json, fiona
from fiona.transform import transform_geom

SRC = r'C:\Users\abdo\Downloads\GDB_extracted\Sakuki.gdb'   # unzip GDB.zip here first
KEEP = {'28-112', '34-82', '401-61', '401-2', '91-25'}

out = []
with fiona.open(SRC, layer='sakoki_with_deed') as src:
    for f in src:
        p = dict(f['properties'])
        if p['Geo_ID'] not in KEEP:
            continue
        geom = transform_geom(src.crs, 'EPSG:4326', f['geometry'], precision=7)
        for k, v in list(p.items()):
            if hasattr(v, 'isoformat'):
                p[k] = v.isoformat()
        out.append({'type': 'Feature', 'properties': p, 'geometry': geom})

# Synthesised co-owner: this GDB has no (Geo_ID, Deed_No) pair with two owners,
# so the importer's group-by-deed path would otherwise go untested.
lead = next(f for f in out if f['properties']['Geo_ID'] == '91-25')
mate = json.loads(json.dumps(lead))
mate['properties']['Name'] = 'شريك تجريبي'
mate['properties']['Woner_ID'] = '9999999999'
out.append(mate)

with open('tests/fixtures/import/parcels.geojson', 'w', encoding='utf-8') as fh:
    json.dump({'type': 'FeatureCollection', 'features': out}, fh, ensure_ascii=False, indent=1)

print('features written:', len(out))
PY
```

Expected: `features written: 8` — `91-25` plus its synthesized co-owner (2), `28-112` twice, `34-82` twice, `401-61`, `401-2`.

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\ParcelGeoJsonImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ParcelGeoJsonImporterTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/fixtures/import/parcels.geojson');
    }

    private function importer(): ParcelGeoJsonImporter
    {
        return app(ParcelGeoJsonImporter::class);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'parcels' => DB::table('parcels')->count(),
            'deeds' => DB::table('deeds')->count(),
            'owners' => DB::table('owners')->count(),
            'deed_owners' => DB::table('deed_owners')->count(),
            'boundaries' => DB::table('parcel_boundaries')->count(),
        ];
    }

    public function test_analyze_writes_nothing(): void
    {
        $before = $this->counts();

        $preview = $this->importer()->analyze($this->fixture());

        $this->assertSame($before, $this->counts(), 'analyze() must never write');
        $this->assertSame(5, $preview->willCreate, 'five distinct Geo_IDs in the fixture');
        $this->assertSame(0, $preview->willUpdate);
    }

    public function test_analyze_reports_updates_once_the_data_exists(): void
    {
        $this->importer()->commit($this->fixture());

        $preview = $this->importer()->analyze($this->fixture());

        $this->assertSame(0, $preview->willCreate);
        $this->assertSame(5, $preview->willUpdate);
    }

    public function test_commit_creates_parcels_deeds_and_owners(): void
    {
        $result = $this->importer()->commit($this->fixture());

        $this->assertSame(5, $result->created);
        $this->assertSame(0, $result->errors);
        $this->assertDatabaseHas('parcels', ['geo_id' => '91-25', 'parcel_no' => '91']);
        $this->assertDatabaseHas('deeds', ['deed_no' => '311608002898']);
    }

    public function test_commit_is_idempotent(): void
    {
        $this->importer()->commit($this->fixture());
        $first = $this->counts();

        $this->importer()->commit($this->fixture());

        $this->assertSame($first, $this->counts(), 'a second commit must not duplicate anything');
    }

    public function test_one_parcel_can_carry_two_deeds(): void
    {
        $this->importer()->commit($this->fixture());

        $parcelId = DB::table('parcels')->where('geo_id', '28-112')->value('id');

        $this->assertSame(2, DB::table('deeds')->where('parcel_id', $parcelId)->count());
    }

    public function test_one_deed_number_can_sit_on_two_parcels(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame(2, DB::table('deeds')->where('deed_no', '911605004832')->count());
    }

    public function test_co_owners_on_one_deed_produce_two_links(): void
    {
        $this->importer()->commit($this->fixture());

        $deedId = DB::table('deeds')->where('deed_no', '311608002898')->value('id');

        $this->assertSame(2, DB::table('deed_owners')->where('deed_id', $deedId)->count());
    }

    public function test_an_owner_is_reused_across_parcels_by_national_id(): void
    {
        $this->importer()->commit($this->fixture());
        $this->importer()->commit($this->fixture());

        $this->assertSame(1, DB::table('owners')->where('national_id', '9999999999')->count());
    }

    public function test_it_stores_the_survey_area_as_measured_area(): void
    {
        $this->importer()->commit($this->fixture());

        $parcelId = DB::table('parcels')->where('geo_id', '91-25')->value('id');
        $measured = DB::table('parcel_boundaries')->where('parcel_id', $parcelId)->value('measured_area');

        $this->assertNotNull($measured, 'Survey_Area must land in measured_area');
        $this->assertEqualsWithDelta(10150.48, (float) $measured, 0.01);
    }

    public function test_it_keeps_hijri_dates_as_plain_text(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame(
            '1442-04-21',
            DB::table('deeds')->where('deed_no', '311608002898')->value('deed_date_hijri')
        );
    }

    public function test_it_maps_numeric_domain_codes_to_enum_values(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame('محدث', DB::table('deeds')->where('deed_no', '311608002898')->value('deed_status'));
    }

    public function test_it_stores_geometry_as_multipolygon_in_4326(): void
    {
        $this->importer()->commit($this->fixture());

        $row = DB::selectOne(
            "SELECT ST_GeometryType(geom) AS type, ST_SRID(geom) AS srid FROM parcels WHERE geo_id = '91-25'"
        );

        $this->assertSame('ST_MultiPolygon', $row->type);
        $this->assertSame(4326, (int) $row->srid);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/ParcelGeoJsonImporterTest.php`
Expected: FAIL — `Class "App\Services\Import\ParcelGeoJsonImporter" not found`.

- [ ] **Step 4: Create the service by moving the command's logic**

Create `app/Services/Import/ParcelGeoJsonImporter.php` with this skeleton, then **move these members verbatim** from `app/Console/Commands/ImportParcelsGeoJson.php`: the constants `DEFAULT_ENGINEERING_OFFICE`, `DEFAULT_COUNTRY`, `DEFAULT_REGION`, `DEFAULT_CITY`, `ENUM_VALUES`, and the methods `importGroup()`, `engineeringOfficeId()`, `cityId()`, `districtId()`, `planId()`, `findOrCreate()`, `enum()`, `hijri()`, `str()`, `num()`.

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports parcels, deeds, owners, boundaries and survey decisions from a
 * GeoJSON export of the survey geodatabase (EPSG:4326).
 *
 * Logic moved here from App\Console\Commands\ImportParcelsGeoJson so the
 * artisan command and the dashboard import run exactly the same code.
 */
final class ParcelGeoJsonImporter implements Importer
{
    // ↓ constants moved verbatim from the command
    private const DEFAULT_ENGINEERING_OFFICE = 'مكتب الإسناد العالمي للاستشارات الهندسية';
    private const DEFAULT_COUNTRY = 'المملكة العربية السعودية';
    private const DEFAULT_REGION = 'منطقة الرياض';
    private const DEFAULT_CITY = 'الدرعية';

    /**
     * Enum values in order — the numeric code in the source maps to the
     * position (1 = first). Must match the definitions in create_enum_types.
     * Codes are documented in docs/gdb-coded-domains.md.
     *
     * @var array<string, list<string>>
     */
    private const ENUM_VALUES = [
        'asset_type' => ['أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع'],
        'land_transaction' => ['مباعة', 'مؤجرة', 'قيد البيع', 'خاصة'],
        'deed_status' => ['محدث', 'قديم'],
        'deed_class' => ['زراعي', 'سكني', 'صناعي'],
        'qrar_source' => ['بلدي', 'مكتب هندسي', 'بدون'],
        'allocation_method' => ['محدد بدقة', 'محدد حسب الموقع العام', 'لم يتم تحديد الموقع'],
        'fall_in' => ['مخطط زراعي', 'مخطط بلدية'],
    ];

    public function analyze(string $sourcePath): ImportPreview
    {
        return $this->previewFeatures($this->readFeatures($sourcePath));
    }

    public function commit(string $sourcePath): ImportResult
    {
        return $this->importFeatures($this->readFeatures($sourcePath));
    }

    /**
     * @param  list<array<string, mixed>>  $features
     */
    public function previewFeatures(array $features): ImportPreview
    {
        $groups = $this->groupByParcelAndDeed($features);

        $create = 0;
        $update = 0;
        $warnings = [];

        foreach ($groups as $group) {
            $geoId = (string) ($group[0]['properties']['Geo_ID'] ?? '');

            if ($geoId === '') {
                $warnings[] = 'A feature has no Geo_ID and will be skipped.';

                continue;
            }

            // Read-only: this is the one query analyze() is allowed to make.
            DB::table('parcels')->where('geo_id', $geoId)->exists() ? $update++ : $create++;
        }

        $geoIds = [];
        foreach ($groups as $group) {
            $geoIds[(string) ($group[0]['properties']['Geo_ID'] ?? '')] = true;
        }

        return new ImportPreview(
            totalItems: count($features),
            willCreate: $create,
            willUpdate: $update,
            unmatched: 0,
            details: [
                'parcels' => count($geoIds),
                'deeds' => count($groups),
            ],
            warnings: $warnings,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $features
     */
    public function importFeatures(array $features): ImportResult
    {
        $groups = $this->groupByParcelAndDeed($features);

        $officeId = $this->engineeringOfficeId();
        $cityId = $this->cityId();

        $stats = ['inserted' => 0, 'updated' => 0, 'deeds' => 0, 'owners' => 0, 'boundaries' => 0, 'decisions' => 0, 'errors' => 0];
        $warnings = [];

        foreach ($groups as $group) {
            try {
                // Regular closure with `use (&$stats)` — an arrow fn would capture
                // $stats by value and silently drop the counters.
                DB::transaction(function () use ($group, $cityId, $officeId, &$stats): void {
                    $this->importGroup($group, $cityId, $officeId, $stats);
                });
            } catch (Throwable $e) {
                $stats['errors']++;
                $warnings[] = ($group[0]['properties']['Geo_ID'] ?? '?').': '.$e->getMessage();
            }
        }

        return new ImportResult(
            created: $stats['inserted'],
            updated: $stats['updated'],
            skipped: 0,
            errors: $stats['errors'],
            details: [
                'deeds' => $stats['deeds'],
                'owners' => $stats['owners'],
                'boundaries' => $stats['boundaries'],
                'decisions' => $stats['decisions'],
            ],
            warnings: $warnings,
        );
    }

    /**
     * A parcel held by several owners appears as one feature per owner,
     * sharing the same Geo_ID + Deed_No.
     *
     * @param  list<array<string, mixed>>  $features
     * @return list<list<array<string, mixed>>>
     */
    private function groupByParcelAndDeed(array $features): array
    {
        $groups = [];

        foreach ($features as $feature) {
            $p = $feature['properties'] ?? [];
            $key = ($p['Geo_ID'] ?? '').'|'.($p['Deed_No'] ?? '');
            $groups[$key][] = $feature;
        }

        return array_values($groups);
    }

    /** @return list<array<string, mixed>> */
    private function readFeatures(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("GeoJSON file not found: {$path}");
        }

        $raw = json_decode((string) file_get_contents($path), true);
        $features = $raw['features'] ?? null;

        if (! is_array($features) || $features === []) {
            throw new RuntimeException('No features found in the GeoJSON file.');
        }

        return array_values($features);
    }

    // ↓ moved verbatim from the command:
    //   importGroup(), engineeringOfficeId(), cityId(), districtId(),
    //   planId(), findOrCreate(), enum(), hijri(), str(), num()
}
```

- [ ] **Step 5: Populate measured_area inside the moved importGroup()**

In the `parcel_boundaries` INSERT that you moved, the value list currently reads `VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())` — the `NULL` is `measured_area`. Replace that `NULL` with `?` and add the bound value in the matching position:

```php
'INSERT INTO parcel_boundaries (parcel_id, n_border, s_border, e_border, w_border,
                                n_dim, s_dim, e_dim, w_dim, measured_area,
                                engineering_office_id, created_at, updated_at)
 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
 ON CONFLICT (parcel_id) DO UPDATE SET
     n_border = EXCLUDED.n_border, s_border = EXCLUDED.s_border,
     e_border = EXCLUDED.e_border, w_border = EXCLUDED.w_border,
     n_dim = EXCLUDED.n_dim, s_dim = EXCLUDED.s_dim,
     e_dim = EXCLUDED.e_dim, w_dim = EXCLUDED.w_dim,
     measured_area = EXCLUDED.measured_area,
     engineering_office_id = COALESCE(parcel_boundaries.engineering_office_id, EXCLUDED.engineering_office_id),
     updated_at = NOW()',
[
    $parcelId,
    $this->str($p['N_Border'] ?? null), $this->str($p['S_Border'] ?? null),
    $this->str($p['E_Border'] ?? null), $this->str($p['W_Border'] ?? null),
    $this->num($p['N_Dim'] ?? null), $this->num($p['S_DIM'] ?? null),
    $this->num($p['E_Dim'] ?? null), $this->num($p['W_Dim'] ?? null),
    $this->num($p['Survey_Area'] ?? null),
    $officeId,
]
```

Note the field is `S_DIM` (uppercase) in the source but `S_Dim_2` in the second set — the geodatabase is inconsistent and the existing code already accounts for it. Do not "correct" it.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/ParcelGeoJsonImporterTest.php`
Expected: PASS, 12 tests.

- [ ] **Step 7: Reduce the artisan command to a wrapper**

Replace the body of `app/Console/Commands/ImportParcelsGeoJson.php`, keeping its signature, description and table output identical so existing operator habits still work:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\ParcelGeoJsonImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportParcelsGeoJson extends Command
{
    protected $signature = 'app:import-parcels-geojson
                            {--file=import/sakoki_with_deed.geojson : GeoJSON path relative to storage/app}';

    protected $description = 'Import parcels, deeds, owners, boundaries and survey decisions from a GeoJSON export';

    public function handle(ParcelGeoJsonImporter $importer): int
    {
        $path = storage_path('app/'.$this->option('file'));
        $startedAt = now();

        try {
            $result = $importer->commit($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn('✗ '.$warning);
        }

        $this->logSync($startedAt, $result, basename($path));

        $this->table(
            ['قطع جديدة', 'قطع محدّثة', 'صكوك', 'ملاك جدد', 'حدود', 'قرارات', 'أخطاء'],
            [[
                $result->created,
                $result->updated,
                $result->details['deeds'] ?? 0,
                $result->details['owners'] ?? 0,
                $result->details['boundaries'] ?? 0,
                $result->details['decisions'] ?? 0,
                $result->errors,
            ]]
        );

        return $result->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function logSync(\Illuminate\Support\Carbon $startedAt, \App\Services\Import\ImportResult $result, string $source): void
    {
        DB::statement(
            'INSERT INTO sync_log (sync_started_at, sync_finished_at, records_imported, records_updated, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $startedAt,
                now(),
                $result->created,
                $result->updated,
                $result->errors === 0 ? 'success' : 'partial',
                sprintf(
                    'src=%s | new=%d upd=%d deeds=%d owners=%d err=%d',
                    $source, $result->created, $result->updated,
                    $result->details['deeds'] ?? 0, $result->details['owners'] ?? 0, $result->errors
                ),
            ]
        );
    }
}
```

- [ ] **Step 8: Verify the whole suite still passes**

Run: `vendor/bin/phpunit`
Expected: PASS — no existing test regressed.

- [ ] **Step 9: Lint, analyse, commit**

```bash
vendor/bin/pint app/Services/Import app/Console/Commands tests
vendor/bin/phpstan analyse
git add app/Services/Import app/Console/Commands tests
git commit -m "refactor(import): move GeoJSON import into a service and populate measured_area"
```

---

### Task 4: DocumentImporter — filename detection and the deed fan-out fix

**Files:**
- Create: `app/Services/Import/DocumentImporter.php`
- Create: `app/Services/Import/DocumentRule.php`
- Modify: `app/Console/Commands/LinkDeedDocuments.php` (reduce to a wrapper)
- Test: `tests/Feature/Import/DocumentImporterTest.php`

**Interfaces:**
- Consumes: `ArchiveExtractor` (Task 1); `ImportPreview`, `ImportResult`, `Importer` (Task 2).
- Produces: `DocumentImporter implements Importer`; `DocumentRule` enum with cases `Deed = 'deed'`, `SurveyMap = 'survey_map'` and methods `matches(string $stem): bool`, `photoType(): PhotoType`, `subdirectory(): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\PhotoType;
use App\Services\Import\DocumentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class DocumentImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tmp = sys_get_temp_dir().'/di_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    /** @param list<string> $names */
    private function zipOf(array $names): string
    {
        $path = $this->tmp.'/docs.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($names as $name) {
            $zip->addFromString($name, '%PDF-1.4 fake');
        }
        $zip->close();

        return $path;
    }

    /** Creates a parcel on a plan, plus an optional deed, and returns the parcel id. */
    private function makeParcel(string $geoId, string $parcelNo, string $planNo, ?string $deedNo = null): int
    {
        $planId = DB::table('plans')->insertGetId([
            'plan_no' => $planNo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = DB::table('parcels')->insertGetId([
            'geo_id' => $geoId, 'parcel_no' => $parcelNo, 'plan_id' => $planId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($deedNo !== null) {
            DB::table('deeds')->insert([
                'parcel_id' => $parcelId, 'deed_no' => $deedNo,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $parcelId;
    }

    private function importer(): DocumentImporter
    {
        return app(DocumentImporter::class);
    }

    public function test_analyze_writes_nothing(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $zip = $this->zipOf(['311608002898.pdf']);

        $preview = $this->importer()->analyze($zip);

        $this->assertSame(0, DB::table('parcel_photos')->count(), 'analyze() must never write');
        $this->assertSame(1, $preview->totalItems);
        $this->assertSame('deed', $preview->details['rule']);
    }

    public function test_it_detects_the_deed_rule_and_links_by_deed_number(): void
    {
        $parcelId = $this->makeParcel('91-25', '91', '25', '311608002898');

        $result = $this->importer()->commit($this->zipOf(['311608002898.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'photo_type' => PhotoType::Deed->value,
        ]);
    }

    public function test_it_detects_the_survey_rule_and_links_by_parcel_and_plan(): void
    {
        $parcelId = $this->makeParcel('29-623', '29', '623');

        $result = $this->importer()->commit($this->zipOf(['29 - 623.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'photo_type' => PhotoType::BoundarySurvey->value,
        ]);
    }

    public function test_it_handles_non_numeric_plan_numbers(): void
    {
        $parcelId = $this->makeParcel('7-20A', '7', '20A');

        $this->importer()->commit($this->zipOf(['7 - 20A.pdf']));

        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $parcelId]);
    }

    public function test_one_deed_number_on_two_parcels_links_both(): void
    {
        $first = $this->makeParcel('401-61', '401', '61', '911605004832');
        $second = $this->makeParcel('401-2', '401', '2', '911605004832');

        $result = $this->importer()->commit($this->zipOf(['911605004832.pdf']));

        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $first]);
        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $second]);
        $this->assertSame(2, $result->created, 'a deed on two parcels must link to both');
    }

    public function test_the_majority_rule_wins_and_the_minority_is_reported_unmatched(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $this->makeParcel('29-623', '29', '623');

        $preview = $this->importer()->analyze($this->zipOf([
            '311608002898.pdf', '396426002606.pdf', '396426002607.pdf', '29 - 623.pdf',
        ]));

        $this->assertSame('deed', $preview->details['rule']);
        $this->assertSame(3, $preview->unmatched, 'two unknown deeds plus the survey map that does not fit the chosen rule');
    }

    public function test_unmatched_files_are_never_written(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $result = $this->importer()->commit($this->zipOf(['311608002898.pdf', '999999999999.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_it_names_the_unmatched_files_in_the_preview(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $preview = $this->importer()->analyze($this->zipOf(['311608002898.pdf', '999999999999.pdf']));

        $this->assertContains('999999999999.pdf', $preview->details['unmatched_files']);
    }

    public function test_committing_twice_does_not_duplicate_documents(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $zip = $this->zipOf(['311608002898.pdf']);

        $this->importer()->commit($zip);
        $this->importer()->commit($zip);

        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_it_ignores_entries_that_are_not_pdfs(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $preview = $this->importer()->analyze($this->zipOf(['311608002898.pdf', 'readme.txt']));

        $this->assertSame(1, $preview->totalItems, 'non-PDF entries are not counted as items');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/DocumentImporterTest.php`
Expected: FAIL — `Class "App\Services\Import\DocumentImporter" not found`.

- [ ] **Step 3: Write DocumentRule**

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\PhotoType;

/**
 * How a PDF filename identifies the parcel it belongs to.
 *
 * Neither side of the survey-map rule may assume digits — plan "20A" exists.
 */
enum DocumentRule: string
{
    case Deed = 'deed';
    case SurveyMap = 'survey_map';

    public function matches(string $stem): bool
    {
        return match ($this) {
            self::Deed => preg_match('/^\d{10,14}$/', $stem) === 1,
            self::SurveyMap => preg_match('/^(.+?)\s*-\s*(.+?)$/u', $stem) === 1,
        };
    }

    public function photoType(): PhotoType
    {
        return match ($this) {
            self::Deed => PhotoType::Deed,
            self::SurveyMap => PhotoType::BoundarySurvey,
        };
    }

    public function subdirectory(): string
    {
        return match ($this) {
            self::Deed => 'documents/deeds',
            self::SurveyMap => 'documents/surveys',
        };
    }
}
```

Note the ordering constraint: `Deed` is checked first because an all-digits stem must never be read as `parcel - plan`.

- [ ] **Step 4: Write DocumentImporter**

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Links a ZIP of PDFs to the parcels they belong to.
 *
 * The naming rule is inferred from the majority of entries and reported for
 * confirmation. Files that do not fit the chosen rule are reported unmatched
 * and never written — a silent second guess is how a survey map ends up filed
 * as a deed.
 */
final class DocumentImporter implements Importer
{
    public function __construct(private readonly ArchiveExtractor $extractor) {}

    public function analyze(string $sourcePath): ImportPreview
    {
        [$rule, $matched, $unmatched] = $this->inspect($sourcePath);

        return new ImportPreview(
            totalItems: count($matched) + count($unmatched),
            willCreate: array_sum(array_map(fn (array $m): int => count($m['parcel_ids']), $matched)),
            willUpdate: 0,
            unmatched: count($unmatched),
            details: [
                'rule' => $rule?->value,
                'photo_type' => $rule?->photoType()->value,
                'unmatched_files' => array_slice($unmatched, 0, 50),
            ],
            warnings: $unmatched === [] ? [] : [count($unmatched).' file(s) matched no parcel and will be skipped.'],
        );
    }

    public function commit(string $sourcePath): ImportResult
    {
        [$rule, $matched, $unmatched] = $this->inspect($sourcePath);

        if ($rule === null) {
            return new ImportResult(0, 0, count($unmatched), 0, ['rule' => null], ['No naming rule matched any file in the archive.']);
        }

        $disk = Storage::disk('public');
        $created = 0;

        foreach ($matched as $entry) {
            $stored = $rule->subdirectory().'/'.$entry['filename'];
            $disk->put($stored, (string) file_get_contents($entry['path']));

            foreach ($entry['parcel_ids'] as $parcelId) {
                DB::table('parcel_photos')->updateOrInsert(
                    ['parcel_id' => $parcelId, 'photo_type' => $rule->photoType()->value],
                    ['photo_url' => '/storage/'.$stored, 'updated_at' => now(), 'created_at' => now()],
                );
                $created++;
            }
        }

        return new ImportResult(
            created: $created,
            updated: 0,
            skipped: count($unmatched),
            errors: 0,
            details: [
                'rule' => $rule->value,
                'photo_type' => $rule->photoType()->value,
                'unmatched_files' => array_slice($unmatched, 0, 50),
            ],
            warnings: [],
        );
    }

    /**
     * @return array{0: DocumentRule|null, 1: list<array{filename: string, path: string, parcel_ids: list<int>}>, 2: list<string>}
     */
    private function inspect(string $sourcePath): array
    {
        $dir = $this->extractor->extract($sourcePath, dirname($sourcePath).'/extracted');

        $pdfs = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $pdfs[] = $file->getPathname();
            }
        }

        $rule = $this->detectRule($pdfs);
        $matched = [];
        $unmatched = [];

        foreach ($pdfs as $path) {
            $filename = basename($path);
            $stem = trim(pathinfo($path, PATHINFO_FILENAME));

            if ($rule === null || ! $rule->matches($stem)) {
                $unmatched[] = $filename;

                continue;
            }

            $parcelIds = $this->parcelIdsFor($rule, $stem);

            if ($parcelIds === []) {
                $unmatched[] = $filename;

                continue;
            }

            $matched[] = ['filename' => $filename, 'path' => $path, 'parcel_ids' => $parcelIds];
        }

        return [$rule, $matched, $unmatched];
    }

    /** Picks the rule matching the most filenames. Deed wins ties — it is the stricter pattern. */
    private function detectRule(array $pdfs): ?DocumentRule
    {
        $best = null;
        $bestCount = 0;

        foreach (DocumentRule::cases() as $rule) {
            $count = 0;
            foreach ($pdfs as $path) {
                if ($rule->matches(trim(pathinfo($path, PATHINFO_FILENAME)))) {
                    $count++;
                }
            }

            if ($count > $bestCount) {
                $best = $rule;
                $bestCount = $count;
            }
        }

        return $bestCount > 0 ? $best : null;
    }

    /**
     * A deed number can sit on more than one parcel, so this returns every
     * match rather than the first one.
     *
     * @return list<int>
     */
    private function parcelIdsFor(DocumentRule $rule, string $stem): array
    {
        if ($rule === DocumentRule::Deed) {
            return DB::table('deeds')->where('deed_no', $stem)->pluck('parcel_id')->unique()->values()->all();
        }

        preg_match('/^(.+?)\s*-\s*(.+?)$/u', $stem, $m);

        return DB::table('parcels')
            ->join('plans', 'plans.id', '=', 'parcels.plan_id')
            ->where('parcels.parcel_no', trim($m[1]))
            ->where('plans.plan_no', trim($m[2]))
            ->pluck('parcels.id')
            ->all();
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/DocumentImporterTest.php`
Expected: PASS, 10 tests.

- [ ] **Step 6: Reduce LinkDeedDocuments to a wrapper**

The command currently scans `storage/app/public/documents/deeds` directly and matches with `->first()`. Rewrite `handle()` to zip that directory into a temporary archive and hand it to `DocumentImporter`, so the fan-out fix applies to the CLI path too:

```php
public function handle(DocumentImporter $importer): int
{
    $dir = storage_path('app/public/documents/deeds');

    if (! is_dir($dir)) {
        $this->error("Directory not found: {$dir}");

        return self::FAILURE;
    }

    $zipPath = storage_path('app/private/link-deeds-'.uniqid().'.zip');
    $zip = new \ZipArchive;
    $zip->open($zipPath, \ZipArchive::CREATE);

    foreach (glob($dir.'/*.pdf') ?: [] as $pdf) {
        $zip->addFile($pdf, basename($pdf));
    }

    $zip->close();

    try {
        $result = $importer->commit($zipPath);
    } finally {
        @unlink($zipPath);
    }

    $this->info("Deed documents linked: {$result->created}");

    if ($result->skipped > 0) {
        $this->warn('PDFs with no matching deed ('.$result->skipped.'): '
            .implode(', ', array_slice($result->details['unmatched_files'] ?? [], 0, 15)));
    }

    return self::SUCCESS;
}
```

- [ ] **Step 7: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 8: Lint, analyse, commit**

```bash
vendor/bin/pint app/Services/Import app/Console/Commands tests
vendor/bin/phpstan analyse
git add app/Services/Import app/Console/Commands tests
git commit -m "feat(import): add document importer with rule detection and multi-parcel deed linking"
```

---

### Task 5: GdbConverter and GdbImporter

**Files:**
- Create: `config/imports.php`
- Create: `app/Services/Import/GdbConverter.php`, `app/Services/Import/GdbImporter.php`
- Modify: `.env.example`
- Test: `tests/Feature/Import/GdbConverterTest.php`

**Interfaces:**
- Consumes: `ArchiveExtractor` (Task 1), `ParcelGeoJsonImporter` (Task 3).
- Produces: `GdbConverter::convert(string $sourcePath, string $workDir): string` (returns GeoJSON path, throws `ArchiveException`); `GdbConverter::isAvailable(): bool`; `GdbImporter implements Importer`.

- [ ] **Step 1: Write config/imports.php**

```php
<?php

declare(strict_types=1);

return [
    // Absolute path to the GDAL ogr2ogr binary. A File Geodatabase cannot be
    // read from PHP, so the GDB import shells out to this.
    'ogr2ogr_path' => env('IMPORT_OGR2OGR_PATH', 'ogr2ogr'),

    // Upload chunk size, in bytes. Kept under post_max_size so the browser can
    // send large archives without a server configuration change.
    'chunk_bytes' => (int) env('IMPORT_CHUNK_BYTES', 2 * 1024 * 1024),

    'max_upload_bytes' => (int) env('IMPORT_MAX_UPLOAD_BYTES', 512 * 1024 * 1024),

    'max_archive_entries' => (int) env('IMPORT_MAX_ARCHIVE_ENTRIES', 5000),

    'max_archive_bytes' => (int) env('IMPORT_MAX_ARCHIVE_BYTES', 2 * 1024 * 1024 * 1024),

    // Days to keep a staged upload before PruneImportBatches deletes it. The
    // batch row is kept as history; only the archive is removed.
    'retention_days' => (int) env('IMPORT_RETENTION_DAYS', 7),

    // Run analyze/commit inline instead of on the queue. For hosts with no
    // queue worker, where a queued job would leave the page waiting forever.
    'queue_sync' => (bool) env('IMPORT_QUEUE_SYNC', false),
];
```

Append to `.env.example`:

```
# Data import (dashboard)
IMPORT_OGR2OGR_PATH=ogr2ogr
IMPORT_QUEUE_SYNC=false
```

- [ ] **Step 2: Write the failing test**

```php
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
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/GdbConverterTest.php`
Expected: FAIL — `Class "App\Services\Import\GdbConverter" not found`.

- [ ] **Step 4: Write GdbConverter**

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use Symfony\Component\Process\Process;

/**
 * Turns an uploaded geodatabase into GeoJSON in EPSG:4326.
 *
 * PHP cannot read an ESRI File Geodatabase and no library exists, so this
 * shells out to GDAL's ogr2ogr. A plain .geojson upload is passed straight
 * through, which is also the escape hatch when the host has no GDAL.
 */
final class GdbConverter
{
    private const REQUIRED_FIELDS = ['Geo_ID', 'Deed_No'];

    public function __construct(private readonly ArchiveExtractor $extractor) {}

    public function isAvailable(): bool
    {
        $process = new Process([$this->binary(), '--version']);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    /** @throws ArchiveException */
    public function convert(string $sourcePath, string $workDir): string
    {
        if (str_ends_with(strtolower($sourcePath), '.geojson') || str_ends_with(strtolower($sourcePath), '.json')) {
            return $sourcePath;
        }

        $extracted = $this->extractor->extract($sourcePath, $workDir.'/extracted');
        $gdb = $this->findGeodatabase($extracted);

        if ($gdb === null) {
            throw new ArchiveException('No .gdb directory was found inside the archive.');
        }

        if (! $this->isAvailable()) {
            throw new ArchiveException(
                'The ogr2ogr binary (GDAL) was not found, so a geodatabase cannot be converted. '
                .'Install GDAL, set IMPORT_OGR2OGR_PATH to its full path, or upload a GeoJSON export instead.'
            );
        }

        $layer = $this->pickLayer($gdb);
        $output = $workDir.'/converted.geojson';

        $process = new Process([
            $this->binary(), '-f', 'GeoJSON', '-t_srs', 'EPSG:4326', $output, $gdb, $layer,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($output)) {
            throw new ArchiveException('ogr2ogr failed to convert the geodatabase: '.trim($process->getErrorOutput()));
        }

        return $output;
    }

    private function binary(): string
    {
        return (string) config('imports.ogr2ogr_path', 'ogr2ogr');
    }

    private function findGeodatabase(string $root): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && str_ends_with(strtolower($entry->getFilename()), '.gdb')) {
                return $entry->getPathname();
            }
        }

        return null;
    }

    /**
     * Picks the layer carrying both Geo_ID and Deed_No. A geodatabase holds
     * several layers (this one also has Adjacent_Parcel) and only the parcel
     * layer can be imported.
     *
     * @throws ArchiveException
     */
    private function pickLayer(string $gdb): string
    {
        $process = new Process([$this->binary(), '-al', '-so', '-json', $gdb]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            // ogrinfo-style JSON is unavailable on some builds; fall back to the
            // conventional layer name rather than failing outright.
            return 'sakoki_with_deed';
        }

        $info = json_decode($process->getOutput(), true);
        $found = [];

        foreach ($info['layers'] ?? [] as $layer) {
            $name = (string) ($layer['name'] ?? '');
            $found[] = $name;
            $fields = array_map(fn (array $f): string => (string) ($f['name'] ?? ''), $layer['fields'] ?? []);

            if (count(array_intersect(self::REQUIRED_FIELDS, $fields)) === count(self::REQUIRED_FIELDS)) {
                return $name;
            }
        }

        throw new ArchiveException(
            'No layer carrying both Geo_ID and Deed_No was found. Layers present: '.implode(', ', $found)
        );
    }
}
```

Note: `-al -so -json` is served by `ogrinfo` on some GDAL builds. The fallback to the conventional layer name keeps a working import on those builds rather than failing; the real integration test covers the happy path.

- [ ] **Step 5: Bind ArchiveExtractor to its config**

`ArchiveExtractor` takes its caps as constructor arguments so the unit tests can drive them directly, which means nothing reads `imports.max_archive_entries` / `imports.max_archive_bytes` until it is bound. Register it in `app/Providers/AppServiceProvider.php`'s `register()`:

```php
$this->app->bind(ArchiveExtractor::class, fn (): ArchiveExtractor => new ArchiveExtractor(
    maxEntries: (int) config('imports.max_archive_entries'),
    maxTotalBytes: (int) config('imports.max_archive_bytes'),
));
```

Add `use App\Services\Import\ArchiveExtractor;` to the provider's imports. Without this the config keys are dead and the defaults silently win.

- [ ] **Step 6: Write GdbImporter**

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

/** Composes conversion and parcel import for an uploaded geodatabase. */
final class GdbImporter implements Importer
{
    public function __construct(
        private readonly GdbConverter $converter,
        private readonly ParcelGeoJsonImporter $parcels,
    ) {}

    public function analyze(string $sourcePath): ImportPreview
    {
        return $this->parcels->analyze($this->converter->convert($sourcePath, $this->workDir($sourcePath)));
    }

    public function commit(string $sourcePath): ImportResult
    {
        return $this->parcels->commit($this->converter->convert($sourcePath, $this->workDir($sourcePath)));
    }

    private function workDir(string $sourcePath): string
    {
        return dirname($sourcePath).'/work';
    }
}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/GdbConverterTest.php`
Expected: PASS — 4 passing, 1 skipped unless `ogr2ogr` and the fixture are both present.

- [ ] **Step 8: Lint, analyse, commit**

```bash
vendor/bin/pint app/Services/Import config tests
vendor/bin/phpstan analyse
git add app/Services/Import config/imports.php .env.example tests
git commit -m "feat(import): convert uploaded geodatabases to GeoJSON via ogr2ogr"
```

---

### Task 6: import_batches table and the ImportBatch model

**Files:**
- Create: `database/migrations/2026_09_01_120000_create_import_batches_table.php`
- Create: `app/Models/ImportBatch.php`
- Test: `tests/Feature/Import/ImportBatchTest.php`

**Interfaces:**
- Consumes: `ImportKind`, `ImportStatus` (Task 2).
- Produces: `ImportBatch` model with `$fillable`, casts, `user(): BelongsTo`, `transitionTo(ImportStatus $status, array $attributes = []): bool`, `markFailed(string $message): void`, `scopeStale(Builder $q, int $days): Builder`.

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // kind and status are plain strings cast to PHP enums, not native
            // Postgres enums. This table's native-enum neighbours (deed_status,
            // asset_type, …) mirror fixed ArcGIS coded domains — source data.
            // Import lifecycle is application state that changes with the code,
            // and a native enum would need a migration to alter.
            $table->string('kind', 20);
            $table->string('status', 20)->index();

            $table->string('original_filename');
            $table->bigInteger('byte_size');
            $table->integer('received_chunks')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('stored_path', 500)->nullable();

            $table->jsonb('preview')->nullable();
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportBatchTest extends TestCase
{
    use RefreshDatabase;

    private function batch(ImportStatus $status = ImportStatus::Uploading): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'batch'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        return ImportBatch::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Gdb,
            'status' => $status,
            'original_filename' => 'GDB.zip',
            'byte_size' => 1024,
        ]);
    }

    public function test_it_casts_kind_and_status_to_enums(): void
    {
        $batch = $this->batch();

        $this->assertSame(ImportKind::Gdb, $batch->fresh()->kind);
        $this->assertSame(ImportStatus::Uploading, $batch->fresh()->status);
    }

    public function test_it_allows_a_legal_transition(): void
    {
        $batch = $this->batch();

        $this->assertTrue($batch->transitionTo(ImportStatus::Uploaded));
        $this->assertSame(ImportStatus::Uploaded, $batch->fresh()->status);
    }

    public function test_it_refuses_to_commit_without_a_preview(): void
    {
        $batch = $this->batch(ImportStatus::Uploaded);

        $this->assertFalse($batch->transitionTo(ImportStatus::Committing));
        $this->assertSame(ImportStatus::Uploaded, $batch->fresh()->status);
    }

    public function test_it_refuses_a_second_commit(): void
    {
        $batch = $this->batch(ImportStatus::Previewed);

        $this->assertTrue($batch->transitionTo(ImportStatus::Committing));
        $this->assertFalse($batch->fresh()->transitionTo(ImportStatus::Committing));
    }

    public function test_marking_failed_records_the_message(): void
    {
        $batch = $this->batch(ImportStatus::Analyzing);

        $batch->markFailed('ogr2ogr was not found');

        $this->assertSame(ImportStatus::Failed, $batch->fresh()->status);
        $this->assertSame('ogr2ogr was not found', $batch->fresh()->error_message);
    }

    public function test_the_stale_scope_finds_old_batches(): void
    {
        $old = $this->batch();
        $old->forceFill(['created_at' => now()->subDays(30)])->save();
        $this->batch();

        $this->assertSame(1, ImportBatch::query()->stale(7)->count());
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportBatchTest.php`
Expected: FAIL — `Class "App\Models\ImportBatch" not found`.

- [ ] **Step 4: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property ImportKind $kind
 * @property ImportStatus $status
 * @property array<string, mixed>|null $preview
 * @property array<string, mixed>|null $result
 */
class ImportBatch extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'kind', 'status', 'original_filename',
        'byte_size', 'received_chunks', 'checksum', 'stored_path',
        'preview', 'result', 'error_message', 'analyzed_at', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ImportKind::class,
            'status' => ImportStatus::class,
            'preview' => 'array',
            'result' => 'array',
            'analyzed_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Moves the batch forward, refusing any transition the lifecycle forbids.
     *
     * Returns false rather than throwing: a double-clicked Confirm is a normal
     * thing for a browser to do, not an exceptional one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(ImportStatus $status, array $attributes = []): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->forceFill([...$attributes, 'status' => $status])->save();

        return true;
    }

    public function markFailed(string $message): void
    {
        $this->forceFill(['status' => ImportStatus::Failed, 'error_message' => $message])->save();
    }

    /**
     * @param  Builder<ImportBatch>  $query
     * @return Builder<ImportBatch>
     */
    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days))
            ->whereNotNull('stored_path');
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportBatchTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 6: Lint, analyse, commit**

```bash
vendor/bin/pint app/Models database/migrations tests
vendor/bin/phpstan analyse
git add app/Models database/migrations tests
git commit -m "feat(import): add import_batches table and lifecycle model"
```

---

### Task 7: ImporterFactory and the analyze/commit jobs

**Files:**
- Create: `app/Services/Import/ImporterFactory.php`
- Create: `app/Jobs/AnalyzeImportBatch.php`, `app/Jobs/CommitImportBatch.php`
- Test: `tests/Feature/Import/ImportJobsTest.php`

**Interfaces:**
- Consumes: `ImportBatch` (Task 6); `GdbImporter` (Task 5); `DocumentImporter` (Task 4).
- Produces: `ImporterFactory::for(ImportKind $kind): Importer`; `AnalyzeImportBatch::__construct(int $batchId)`; `CommitImportBatch::__construct(int $batchId)`; `ImportBatch::dispatchAnalysis()` and `ImportBatch::dispatchCommit()` helpers honouring `imports.queue_sync`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Jobs\AnalyzeImportBatch;
use App\Jobs\CommitImportBatch;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

final class ImportJobsTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/jobs_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function documentsBatch(ImportStatus $status): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'jobs'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $planId = DB::table('plans')->insertGetId(['plan_no' => '25', 'created_at' => now(), 'updated_at' => now()]);
        $parcelId = DB::table('parcels')->insertGetId([
            'geo_id' => '91-25', 'parcel_no' => '91', 'plan_id' => $planId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('deeds')->insert([
            'parcel_id' => $parcelId, 'deed_no' => '311608002898',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zipPath = $this->tmp.'/docs.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('311608002898.pdf', '%PDF-1.4 fake');
        $zip->close();

        return ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => $status,
            'original_filename' => 'docs.zip',
            'byte_size' => filesize($zipPath),
            'stored_path' => $zipPath,
        ]);
    }

    public function test_analyze_stores_a_preview_and_writes_nothing(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);

        (new AnalyzeImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Previewed, $batch->status);
        $this->assertSame(1, $batch->preview['total_items']);
        $this->assertNotNull($batch->analyzed_at);
        $this->assertSame(0, DB::table('parcel_photos')->count());
    }

    public function test_commit_writes_and_completes(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Previewed);

        (new CommitImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->result['created']);
        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_commit_refuses_a_batch_that_was_never_previewed(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);

        (new CommitImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Uploaded, $batch->status);
        $this->assertSame(0, DB::table('parcel_photos')->count());
    }

    public function test_commit_runs_only_once_for_a_batch(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Previewed);

        (new CommitImportBatch($batch->id))->handle();
        (new CommitImportBatch($batch->id))->handle();

        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_a_failure_records_the_message(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);
        $batch->forceFill(['stored_path' => $this->tmp.'/missing.zip'])->save();

        (new AnalyzeImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Failed, $batch->status);
        $this->assertNotEmpty($batch->error_message);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportJobsTest.php`
Expected: FAIL — `Class "App\Jobs\AnalyzeImportBatch" not found`.

- [ ] **Step 3: Write ImporterFactory**

```php
<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportKind;
use Illuminate\Contracts\Container\Container;

final class ImporterFactory
{
    public function __construct(private readonly Container $container) {}

    public function for(ImportKind $kind): Importer
    {
        return match ($kind) {
            ImportKind::Gdb => $this->container->make(GdbImporter::class),
            ImportKind::Documents => $this->container->make(DocumentImporter::class),
        };
    }
}
```

- [ ] **Step 4: Write the jobs**

`app/Jobs/AnalyzeImportBatch.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\Import\ImporterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class AnalyzeImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);

        if ($batch === null || ! $batch->transitionTo(ImportStatus::Analyzing)) {
            return;
        }

        try {
            $preview = app(ImporterFactory::class)->for($batch->kind)->analyze((string) $batch->stored_path);

            $batch->transitionTo(ImportStatus::Previewed, [
                'preview' => $preview->toArray(),
                'analyzed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $batch->markFailed($e->getMessage());
        }
    }
}
```

`app/Jobs/CommitImportBatch.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Services\Import\ImporterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class CommitImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);

        // Only Previewed may become Committing, so a batch that was never
        // analysed and a batch already committed both fall out here, before
        // any work happens. This is the whole double-write guard.
        if ($batch === null || ! $batch->transitionTo(ImportStatus::Committing)) {
            return;
        }

        try {
            $result = app(ImporterFactory::class)->for($batch->kind)->commit((string) $batch->stored_path);

            $batch->transitionTo(ImportStatus::Completed, [
                'result' => $result->toArray(),
                'committed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $batch->markFailed($e->getMessage());
        }
    }
}
```

- [ ] **Step 5: Add the dispatch helpers to ImportBatch**

```php
public function dispatchAnalysis(): void
{
    config('imports.queue_sync')
        ? AnalyzeImportBatch::dispatchSync($this->id)
        : AnalyzeImportBatch::dispatch($this->id);
}

public function dispatchCommit(): void
{
    config('imports.queue_sync')
        ? CommitImportBatch::dispatchSync($this->id)
        : CommitImportBatch::dispatch($this->id);
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportJobsTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Lint, analyse, commit**

```bash
vendor/bin/pint app/Jobs app/Services/Import app/Models tests
vendor/bin/phpstan analyse
git add app/Jobs app/Services/Import app/Models tests
git commit -m "feat(import): add importer factory and queued analyze/commit jobs"
```

---

### Task 8: Chunked upload endpoints

**Files:**
- Create: `app/Http/Controllers/ImportUploadController.php`
- Modify: `routes/web.php`
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `app/Livewire/Settings/RoleManager.php`
- Test: `tests/Feature/Import/ImportUploadTest.php`

**Interfaces:**
- Consumes: `ImportBatch` (Task 6), `ImportKind`/`ImportStatus` (Task 2).
- Produces: routes `imports.upload.create` (`POST /imports/upload`), `imports.upload.chunk` (`POST /imports/upload/{uuid}/chunk`), `imports.upload.complete` (`POST /imports/upload/{uuid}/complete`). New permission `imports.create`.

- [ ] **Step 1: Add the permission**

In `database/seeders/RolesAndPermissionsSeeder.php`, add `'imports.create'` to the `PERMISSIONS` array. `super_admin` picks it up through the `null` config and `manager` through `except`; `engineer`'s explicit `only` list must **not** include it.

In `app/Livewire/Settings/RoleManager.php`, add to `GROUPS`:

```php
'imports' => ['imports.create'],
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'upload'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');
        $user->givePermissionTo('imports.create');

        return $user;
    }

    private function engineer(): User
    {
        $user = User::create([
            'name' => 'مساح', 'email' => 'eng'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');

        return $user;
    }

    private function start(User $user, int $size = 12, string $name = 'docs.zip'): string
    {
        return $this->actingAs($user)
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => $name, 'byte_size' => $size,
            ])
            ->json('uuid');
    }

    public function test_a_user_without_the_permission_cannot_start_an_upload(): void
    {
        $this->actingAs($this->engineer())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 10,
            ])
            ->assertForbidden();
    }

    public function test_it_creates_a_batch_in_the_uploading_state(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->postJson(route('imports.upload.create'), [
            'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 10,
        ]);

        $response->assertOk()->assertJsonStructure(['uuid', 'chunk_bytes']);
        $this->assertDatabaseHas('import_batches', [
            'uuid' => $response->json('uuid'),
            'status' => ImportStatus::Uploading->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_it_rejects_a_rar_upload(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.rar', 'byte_size' => 10,
            ])
            ->assertStatus(422);
    }

    public function test_it_rejects_a_file_over_the_size_cap(): void
    {
        config(['imports.max_upload_bytes' => 100]);

        $this->actingAs($this->admin())
            ->postJson(route('imports.upload.create'), [
                'kind' => 'documents', 'filename' => 'docs.zip', 'byte_size' => 101,
            ])
            ->assertStatus(422);
    }

    public function test_it_accepts_chunks_in_order(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 12);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'hello '),
        ])->assertOk()->assertJson(['next_index' => 1]);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 1, 'chunk' => UploadedFile::fake()->createWithContent('c1', 'world!'),
        ])->assertOk()->assertJson(['next_index' => 2]);
    }

    public function test_an_out_of_order_chunk_is_rejected_with_the_expected_index(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 5, 'chunk' => UploadedFile::fake()->createWithContent('c5', 'oops'),
        ])->assertStatus(409)->assertJson(['expected_index' => 0]);
    }

    public function test_completing_with_the_wrong_size_fails_the_batch(): void
    {
        $user = $this->admin();
        $uuid = $this->start($user, 999);

        $this->actingAs($user)->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'short'),
        ])->assertOk();

        $this->actingAs($user)->postJson(route('imports.upload.complete', $uuid))->assertStatus(422);

        $this->assertSame(ImportStatus::Failed, ImportBatch::where('uuid', $uuid)->first()->status);
    }

    public function test_a_user_cannot_touch_another_users_batch(): void
    {
        $uuid = $this->start($this->admin());

        $this->actingAs($this->admin())->post(route('imports.upload.chunk', $uuid), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('c0', 'x'),
        ])->assertForbidden();
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportUploadTest.php`
Expected: FAIL — route `imports.upload.create` not defined.

- [ ] **Step 4: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Chunked upload for import archives.
 *
 * The browser sends 2 MB chunks so a large archive fits inside the host's
 * post_max_size without a server configuration change. Chunks must arrive in
 * order; an out-of-order chunk is answered with the index the server actually
 * wants, so a dropped connection resumes instead of corrupting the file.
 */
final class ImportUploadController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $this->authorize('imports.create');

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ImportKind::class)],
            'filename' => ['required', 'string', 'max:255'],
            'byte_size' => ['required', 'integer', 'min:1', 'max:'.config('imports.max_upload_bytes')],
        ]);

        $kind = ImportKind::from($validated['kind']);
        $allowed = $kind === ImportKind::Gdb ? ['zip', 'geojson', 'json'] : ['zip'];
        $extension = strtolower(pathinfo($validated['filename'], PATHINFO_EXTENSION));

        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'message' => __('imports.errors.extension', ['allowed' => implode(', ', $allowed)]),
            ], 422);
        }

        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'kind' => $kind,
            'status' => ImportStatus::Uploading,
            'original_filename' => $validated['filename'],
            'byte_size' => $validated['byte_size'],
        ]);

        return response()->json([
            'uuid' => $batch->uuid,
            'chunk_bytes' => (int) config('imports.chunk_bytes'),
        ]);
    }

    public function chunk(Request $request, string $uuid): JsonResponse
    {
        $batch = $this->ownedBatch($request, $uuid);

        abort_unless($batch->status === ImportStatus::Uploading, 409, __('imports.errors.not_uploading'));

        $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        $index = (int) $request->integer('index');

        if ($index !== $batch->received_chunks) {
            return response()->json([
                'message' => __('imports.errors.out_of_order'),
                'expected_index' => $batch->received_chunks,
            ], 409);
        }

        $partial = $this->partialPath($batch);
        Storage::disk('local')->makeDirectory(dirname($partial));

        file_put_contents(
            Storage::disk('local')->path($partial),
            (string) file_get_contents($request->file('chunk')->getRealPath()),
            FILE_APPEND
        );

        $batch->increment('received_chunks');

        return response()->json(['next_index' => $batch->received_chunks]);
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $batch = $this->ownedBatch($request, $uuid);

        $partial = $this->partialPath($batch);
        $absolute = Storage::disk('local')->path($partial);
        $actual = is_file($absolute) ? (int) filesize($absolute) : 0;

        if ($actual !== (int) $batch->byte_size) {
            $batch->markFailed(__('imports.errors.size_mismatch', ['expected' => $batch->byte_size, 'actual' => $actual]));

            return response()->json(['message' => $batch->error_message], 422);
        }

        $batch->transitionTo(ImportStatus::Uploaded, [
            'stored_path' => $absolute,
            'checksum' => hash_file('sha256', $absolute),
        ]);

        $batch->dispatchAnalysis();

        return response()->json(['uuid' => $batch->uuid, 'status' => $batch->status->value]);
    }

    private function ownedBatch(Request $request, string $uuid): ImportBatch
    {
        $this->authorize('imports.create');

        $batch = ImportBatch::where('uuid', $uuid)->firstOrFail();

        abort_unless($batch->user_id === $request->user()->id, 403);

        return $batch;
    }

    private function partialPath(ImportBatch $batch): string
    {
        return 'imports/'.$batch->uuid.'/source.part';
    }
}
```

- [ ] **Step 5: Add the routes**

Inside the existing `Route::middleware(['auth', 'user.active', 'set.locale'])` group in `routes/web.php`:

```php
// Data import
Route::middleware('can:imports.create')->group(function () {
    Route::get('/imports', fn () => view('imports.index'))->name('imports.index');
    Route::post('/imports/upload', [ImportUploadController::class, 'create'])->name('imports.upload.create');
    Route::post('/imports/upload/{uuid}/chunk', [ImportUploadController::class, 'chunk'])->name('imports.upload.chunk');
    Route::post('/imports/upload/{uuid}/complete', [ImportUploadController::class, 'complete'])->name('imports.upload.complete');
});
```

Add `use App\Http\Controllers\ImportUploadController;` to the imports at the top.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportUploadTest.php`
Expected: PASS, 8 tests. (`imports.index` renders a view that does not exist yet — Task 9 creates it. If the route test fails on the missing view, that is expected; only the upload endpoints are under test here.)

- [ ] **Step 7: Lint, analyse, commit**

```bash
vendor/bin/pint app/Http/Controllers routes database/seeders app/Livewire tests
vendor/bin/phpstan analyse
git add app/Http/Controllers routes database/seeders app/Livewire tests
git commit -m "feat(import): add chunked upload endpoints behind the imports.create permission"
```

---

### Task 9: ImportWizard Livewire component, view, and upload JS

**Files:**
- Create: `app/Livewire/Imports/ImportWizard.php`
- Create: `resources/views/imports/index.blade.php`
- Create: `resources/views/livewire/imports/import-wizard.blade.php`
- Create: `resources/js/import-upload.js`
- Modify: `resources/js/app.js`
- Create: `lang/ar/imports.php`, `lang/en/imports.php`
- Modify: `lang/ar/nav.php`, `lang/en/nav.php`, `lang/ar/permissions.php`, `lang/en/permissions.php`, `lang/ar/settings.php`, `lang/en/settings.php`
- Test: `tests/Feature/Import/ImportWizardTest.php`

**Interfaces:**
- Consumes: `ImportBatch` (Task 6), routes from Task 8.
- Produces: `ImportWizard` with public `?string $batchUuid`, methods `confirm(): void`, `reset(): void`, computed `batch()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Livewire\Imports\ImportWizard;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ImportWizardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'wiz'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Permission::findOrCreate('imports.create', 'web');
        $user->givePermissionTo('imports.create');

        return $user;
    }

    private function batch(User $user, ImportStatus $status): ImportBatch
    {
        return ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => $status,
            'original_filename' => 'docs.zip',
            'byte_size' => 10,
            'stored_path' => '/tmp/nonexistent.zip',
            'preview' => ['total_items' => 27, 'will_create' => 27, 'will_update' => 0, 'unmatched' => 0, 'details' => [], 'warnings' => []],
        ]);
    }

    public function test_the_page_requires_the_permission(): void
    {
        $this->withoutVite();

        $user = User::create([
            'name' => 'مساح', 'email' => 'nope'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->actingAs($user)->get('/imports')->assertForbidden();
    }

    public function test_an_authorised_admin_can_open_the_page(): void
    {
        $this->withoutVite();

        $this->actingAs($this->admin())->get('/imports')->assertOk();
    }

    public function test_it_shows_the_preview_counts(): void
    {
        $user = $this->admin();
        $batch = $this->batch($user, ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->assertSee('27');
    }

    public function test_confirming_dispatches_the_commit(): void
    {
        config(['imports.queue_sync' => true]);
        $user = $this->admin();
        $batch = $this->batch($user, ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm');

        // The staged file does not exist, so the commit fails — which still
        // proves the job ran rather than being silently skipped.
        $this->assertContains($batch->fresh()->status, [ImportStatus::Completed, ImportStatus::Failed]);
        $this->assertNotSame(ImportStatus::Previewed, $batch->fresh()->status);
    }

    public function test_confirming_someone_elses_batch_is_forbidden(): void
    {
        $owner = $this->admin();
        $batch = $this->batch($owner, ImportStatus::Previewed);

        Livewire::actingAs($this->admin())
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm')
            ->assertForbidden();
    }

    public function test_a_user_without_the_permission_cannot_call_confirm(): void
    {
        $user = User::create([
            'name' => 'مساح', 'email' => 'nc'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $batch = $this->batch($this->admin(), ImportStatus::Previewed);

        Livewire::actingAs($user)
            ->test(ImportWizard::class, ['batchUuid' => $batch->uuid])
            ->call('confirm')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportWizardTest.php`
Expected: FAIL — `Class "App\Livewire\Imports\ImportWizard" not found`.

- [ ] **Step 3: Write the component**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ImportWizard extends Component
{
    public ?string $batchUuid = null;

    public function mount(?string $batchUuid = null): void
    {
        $this->batchUuid = $batchUuid;
    }

    public function batch(): ?ImportBatch
    {
        return $this->batchUuid === null
            ? null
            : ImportBatch::where('uuid', $this->batchUuid)->first();
    }

    /**
     * Livewire actions are client-callable, so this authorizes server-side
     * rather than relying on the blade @can that hides the button.
     */
    public function confirm(): void
    {
        $this->authorize('imports.create');

        $batch = $this->batch();

        abort_if($batch === null, 404);
        abort_unless($batch->user_id === auth()->id(), 403);

        if ($batch->status !== ImportStatus::Previewed) {
            return;
        }

        $batch->dispatchCommit();
    }

    public function startOver(): void
    {
        $this->batchUuid = null;
    }

    public function render(): View
    {
        return view('livewire.imports.import-wizard', [
            'currentBatch' => $this->batch(),
            'recent' => ImportBatch::query()->with('user')->latest()->limit(10)->get(),
        ]);
    }
}
```

- [ ] **Step 4: Write the views**

`resources/views/imports/index.blade.php` follows the same shape as `resources/views/documents/index.blade.php` — copy that file's layout wrapper and replace its Livewire component with `@livewire('imports.import-wizard')`.

`resources/views/livewire/imports/import-wizard.blade.php` switches on `$currentBatch?->status`. Structure it exactly like this, styling the panels with the same Tailwind classes the Documents page already uses:

```blade
<div>
    @php($status = $currentBatch?->status)

    {{-- 1. No batch yet — pick a kind and a file. The upload itself is driven
         by window.uploadImport(); Livewire only learns the uuid at the end. --}}
    @if ($currentBatch === null)
        <div x-data="{ kind: 'gdb', busy: false, progress: 0, error: null }">
            <label class="block mb-2 font-medium">{{ __('imports.kind.label') }}</label>
            <select x-model="kind" class="mb-4 w-full rounded-xl border-gray-300">
                <option value="gdb">{{ __('imports.kind.gdb') }}</option>
                <option value="documents">{{ __('imports.kind.documents') }}</option>
            </select>

            <input type="file" accept=".zip,.geojson,.json" x-ref="file" class="mb-4 block">

            <button type="button"
                    :disabled="busy"
                    @click="
                        busy = true; error = null;
                        try {
                            const uuid = await window.uploadImport($refs.file.files[0], kind, {
                                onProgress: p => progress = Math.round(p * 100)
                            });
                            $wire.set('batchUuid', uuid);
                        } catch (e) { error = e.message } finally { busy = false }
                    "
                    class="rounded-xl bg-primary px-4 py-2 text-white disabled:opacity-50">
                <span x-show="!busy">{{ __('imports.upload') }}</span>
                <span x-show="busy" x-text="`{{ __('imports.uploading') }} ${progress}%`"></span>
            </button>

            <p x-show="error" x-text="error" class="mt-3 text-red-600"></p>
        </div>

    {{-- 2. A job is in flight — poll until it lands. --}}
    @elseif (in_array($status, [\App\Enums\ImportStatus::Uploaded, \App\Enums\ImportStatus::Analyzing, \App\Enums\ImportStatus::Committing], true))
        <div wire:poll.2s class="flex items-center gap-3">
            <span class="material-symbols-outlined animate-spin">progress_activity</span>
            <span>{{ $status === \App\Enums\ImportStatus::Committing ? __('imports.committing') : __('imports.analyzing') }}</span>
        </div>

    {{-- 3. Analysed — show what a commit would do, then wait for a human. --}}
    @elseif ($status === \App\Enums\ImportStatus::Previewed)
        <h2 class="mb-4 text-lg font-bold">{{ __('imports.preview.title') }}</h2>

        <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><dt>{{ __('imports.preview.total') }}</dt><dd class="text-2xl font-bold">{{ $currentBatch->preview['total_items'] }}</dd></div>
            <div><dt>{{ __('imports.preview.will_create') }}</dt><dd class="text-2xl font-bold">{{ $currentBatch->preview['will_create'] }}</dd></div>
            <div><dt>{{ __('imports.preview.will_update') }}</dt><dd class="text-2xl font-bold">{{ $currentBatch->preview['will_update'] }}</dd></div>
            <div><dt>{{ __('imports.preview.unmatched') }}</dt><dd class="text-2xl font-bold">{{ $currentBatch->preview['unmatched'] }}</dd></div>
        </dl>

        @if (! empty($currentBatch->preview['details']['rule']))
            <p class="mb-4">{{ __('imports.preview.rule') }}:
                <strong>{{ __('documents.photo_types.'.$currentBatch->preview['details']['photo_type']) }}</strong>
            </p>
        @endif

        @if (! empty($currentBatch->preview['warnings']))
            <ul class="mb-4 list-disc ps-6 text-amber-700">
                @foreach ($currentBatch->preview['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex gap-3">
            @can('imports.create')
                <button wire:click="confirm" wire:loading.attr="disabled"
                        class="rounded-xl bg-primary px-4 py-2 text-white">{{ __('imports.confirm') }}</button>
            @endcan
            <button wire:click="startOver" class="rounded-xl border px-4 py-2">{{ __('imports.cancel') }}</button>
        </div>

    {{-- 4. Terminal — report and offer another run. --}}
    @else
        @if ($status === \App\Enums\ImportStatus::Completed)
            <h2 class="mb-3 text-lg font-bold text-green-700">{{ __('imports.completed') }}</h2>
            <p>{{ __('imports.preview.will_create') }}: {{ $currentBatch->result['created'] }} —
               {{ __('imports.preview.unmatched') }}: {{ $currentBatch->result['skipped'] }}</p>
        @else
            <h2 class="mb-3 text-lg font-bold text-red-700">{{ __('imports.failed') }}</h2>
            <p class="text-red-700">{{ $currentBatch->error_message }}</p>
        @endif

        <button wire:click="startOver" class="mt-4 rounded-xl border px-4 py-2">{{ __('imports.start_over') }}</button>
    @endif

    {{-- Recent batches, always visible. --}}
    <h3 class="mt-8 mb-3 font-bold">{{ __('imports.recent.title') }}</h3>
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="text-start">{{ __('imports.recent.file') }}</th>
                <th class="text-start">{{ __('imports.recent.uploader') }}</th>
                <th class="text-start">{{ __('imports.recent.status') }}</th>
                <th class="text-start">{{ __('imports.recent.date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recent as $row)
                <tr>
                    <td>{{ $row->original_filename }}</td>
                    <td>{{ $row->user?->name }}</td>
                    <td>{{ __('imports.status.'.$row->status->value) }}</td>
                    <td>{{ $row->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('imports.recent.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
```

- [ ] **Step 5: Write the upload JS**

`resources/js/import-upload.js` — slices the file and posts chunks sequentially, honouring the server's `expected_index` on a 409:

```js
export async function uploadImport(file, kind, { onProgress } = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };

    const started = await fetch('/imports/upload', {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({ kind, filename: file.name, byte_size: file.size }),
    });

    if (!started.ok) throw new Error((await started.json()).message);

    const { uuid, chunk_bytes: chunkBytes } = await started.json();

    let index = 0;
    while (index * chunkBytes < file.size) {
        const slice = file.slice(index * chunkBytes, (index + 1) * chunkBytes);
        const body = new FormData();
        body.append('index', String(index));
        body.append('chunk', slice, 'chunk');

        const response = await fetch(`/imports/upload/${uuid}/chunk`, { method: 'POST', headers, body });

        if (response.status === 409) {
            // The server tells us which chunk it actually wants; resync rather
            // than retrying blindly, which is what makes a dropped connection
            // resumable instead of corrupting the assembled file.
            index = (await response.json()).expected_index;
            continue;
        }

        if (!response.ok) throw new Error('Chunk upload failed');

        index = (await response.json()).next_index;
        onProgress?.(Math.min(1, (index * chunkBytes) / file.size));
    }

    const completed = await fetch(`/imports/upload/${uuid}/complete`, { method: 'POST', headers });

    if (!completed.ok) throw new Error((await completed.json()).message);

    return uuid;
}
```

Import it from `resources/js/app.js` and expose it as `window.uploadImport` so the blade can call it.

- [ ] **Step 6: Write the lang files**

`lang/ar/imports.php`:

```php
<?php

declare(strict_types=1);

return [
    'title' => 'استيراد البيانات',
    'kind' => [
        'label' => 'نوع الملف',
        'gdb' => 'قاعدة بيانات جغرافية (GDB مضغوط)',
        'documents' => 'مستندات PDF مضغوطة',
    ],
    'choose_file' => 'اختر ملفاً',
    'upload' => 'رفع',
    'uploading' => 'جارٍ الرفع…',
    'analyzing' => 'جارٍ الفحص…',
    'committing' => 'جارٍ الحفظ…',
    'preview' => [
        'title' => 'نتيجة الفحص',
        'total' => 'إجمالي العناصر',
        'will_create' => 'ستُضاف',
        'will_update' => 'ستُحدَّث',
        'unmatched' => 'غير مطابقة',
        'rule' => 'قاعدة المطابقة',
        'warnings' => 'تنبيهات',
    ],
    'confirm' => 'تأكيد الاستيراد',
    'cancel' => 'إلغاء',
    'completed' => 'اكتمل الاستيراد',
    'failed' => 'فشل الاستيراد',
    'start_over' => 'استيراد ملف آخر',
    'recent' => [
        'title' => 'آخر عمليات الاستيراد',
        'file' => 'الملف',
        'uploader' => 'بواسطة',
        'status' => 'الحالة',
        'date' => 'التاريخ',
        'empty' => 'لا توجد عمليات استيراد بعد.',
    ],
    'status' => [
        'uploading' => 'جارٍ الرفع',
        'uploaded' => 'تم الرفع',
        'analyzing' => 'قيد الفحص',
        'previewed' => 'بانتظار التأكيد',
        'committing' => 'قيد الحفظ',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],
    'errors' => [
        'extension' => 'نوع الملف غير مدعوم. الأنواع المسموحة: :allowed',
        'out_of_order' => 'وصل جزء من الملف بترتيب غير صحيح.',
        'size_mismatch' => 'حجم الملف المستلم (:actual) لا يطابق الحجم المتوقع (:expected).',
        'not_uploading' => 'انتهت مرحلة الرفع لهذه العملية.',
    ],
];
```

`lang/en/imports.php` mirrors it key-for-key:

```php
<?php

declare(strict_types=1);

return [
    'title' => 'Data import',
    'kind' => [
        'label' => 'File type',
        'gdb' => 'Geodatabase (zipped GDB)',
        'documents' => 'Zipped PDF documents',
    ],
    'choose_file' => 'Choose a file',
    'upload' => 'Upload',
    'uploading' => 'Uploading…',
    'analyzing' => 'Analysing…',
    'committing' => 'Saving…',
    'preview' => [
        'title' => 'Analysis result',
        'total' => 'Total items',
        'will_create' => 'Will be created',
        'will_update' => 'Will be updated',
        'unmatched' => 'Unmatched',
        'rule' => 'Matching rule',
        'warnings' => 'Warnings',
    ],
    'confirm' => 'Confirm import',
    'cancel' => 'Cancel',
    'completed' => 'Import complete',
    'failed' => 'Import failed',
    'start_over' => 'Import another file',
    'recent' => [
        'title' => 'Recent imports',
        'file' => 'File',
        'uploader' => 'By',
        'status' => 'Status',
        'date' => 'Date',
        'empty' => 'No imports yet.',
    ],
    'status' => [
        'uploading' => 'Uploading',
        'uploaded' => 'Uploaded',
        'analyzing' => 'Analysing',
        'previewed' => 'Awaiting confirmation',
        'committing' => 'Saving',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'errors' => [
        'extension' => 'Unsupported file type. Allowed: :allowed',
        'out_of_order' => 'A file chunk arrived out of order.',
        'size_mismatch' => 'The received file size (:actual) does not match the expected size (:expected).',
        'not_uploading' => 'This import is no longer accepting chunks.',
    ],
];
```

Then add to the existing files:

- `lang/ar/nav.php`: `'imports' => 'استيراد البيانات',` — `lang/en/nav.php`: `'imports' => 'Data import',`
- `lang/ar/permissions.php`: `'imports.create' => 'استيراد البيانات',` — `lang/en/permissions.php`: `'imports.create' => 'Import data',`
- `lang/ar/settings.php`, in the permission-group labels: `'imports' => 'الاستيراد',` — `lang/en/settings.php`: `'imports' => 'Imports',`

- [ ] **Step 7: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/ImportWizardTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 8: Build the assets and run the whole suite**

```bash
npm run build
vendor/bin/phpunit
```

Expected: build succeeds; full suite passes.

- [ ] **Step 9: Lint, analyse, commit**

```bash
vendor/bin/pint app/Livewire tests
vendor/bin/phpstan analyse
git add app/Livewire resources lang tests
git commit -m "feat(import): add the import wizard page with chunked upload and preview"
```

---

### Task 10: Sidebar entry and batch pruning

**Files:**
- Modify: `app/View/Components/Sidebar.php`
- Create: `app/Console/Commands/PruneImportBatches.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Import/PruneImportBatchesTest.php`

**Interfaces:**
- Consumes: `ImportBatch::scopeStale()` (Task 6).
- Produces: artisan command `app:prune-import-batches`.

- [ ] **Step 1: Add the sidebar entry**

In `app/View/Components/Sidebar.php`, add to `$navItems` — after the `audit-logs.index` entry, since both are administrative:

```php
[
    'route' => 'imports.index',
    'label' => 'nav.imports',
    'icon' => 'cloud_upload',
    'permission' => 'imports.create',
],
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PruneImportBatchesTest extends TestCase
{
    use RefreshDatabase;

    private function batch(int $ageInDays, string $file): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'prune'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        file_put_contents($file, 'staged archive');

        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => ImportStatus::Completed,
            'original_filename' => 'docs.zip',
            'byte_size' => 14,
            'stored_path' => $file,
        ]);

        $batch->forceFill(['created_at' => now()->subDays($ageInDays)])->save();

        return $batch;
    }

    public function test_it_deletes_staged_files_past_the_retention_window(): void
    {
        $old = sys_get_temp_dir().'/old_'.uniqid().'.zip';
        $this->batch(30, $old);

        $this->artisan('app:prune-import-batches')->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
    }

    public function test_it_keeps_the_batch_row_as_history(): void
    {
        $old = sys_get_temp_dir().'/old_'.uniqid().'.zip';
        $batch = $this->batch(30, $old);

        $this->artisan('app:prune-import-batches');

        $this->assertDatabaseHas('import_batches', ['id' => $batch->id]);
        $this->assertNull($batch->fresh()->stored_path);
    }

    public function test_it_leaves_recent_batches_alone(): void
    {
        $recent = sys_get_temp_dir().'/recent_'.uniqid().'.zip';
        $this->batch(1, $recent);

        $this->artisan('app:prune-import-batches');

        $this->assertFileExists($recent);
        @unlink($recent);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/Import/PruneImportBatchesTest.php`
Expected: FAIL — command `app:prune-import-batches` not found.

- [ ] **Step 4: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportBatch;
use Illuminate\Console\Command;

class PruneImportBatches extends Command
{
    protected $signature = 'app:prune-import-batches {--days= : Override the retention window}';

    protected $description = 'Delete staged import archives older than the retention window, keeping the batch rows as history';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('imports.retention_days'));
        $pruned = 0;

        foreach (ImportBatch::query()->stale($days)->get() as $batch) {
            $path = (string) $batch->stored_path;

            if (is_file($path)) {
                @unlink($path);
            }

            // The directory holds the assembled archive plus any extraction
            // work, so remove the whole thing rather than the one file.
            $directory = dirname($path);
            if (is_dir($directory) && str_contains($directory, 'imports')) {
                $this->deleteDirectory($directory);
            }

            $batch->forceFill(['stored_path' => null])->save();
            $pruned++;
        }

        $this->info("Staged import archives pruned: {$pruned}");

        return self::SUCCESS;
    }

    private function deleteDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
```

- [ ] **Step 5: Schedule it**

In `routes/console.php`:

```php
Schedule::command('app:prune-import-batches')->daily();
```

Add `use Illuminate\Support\Facades\Schedule;` if it is not already imported.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/Import/PruneImportBatchesTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 7: Run the whole suite and commit**

```bash
vendor/bin/phpunit
vendor/bin/pint
vendor/bin/phpstan analyse
git add app tests routes
git commit -m "feat(import): add sidebar entry and scheduled pruning of staged archives"
```

---

### Task 11: End-to-end verification against the real archives

Not a code task — the proof that the feature works on the client's actual data. Runs against a scratch database, never production.

**Files:**
- Create: `docs/import-runbook.md`

- [ ] **Step 1: Prepare a scratch database**

```bash
createdb -U postgres sakuki_import_check
psql -U postgres -d sakuki_import_check -c "CREATE EXTENSION postgis;"
```

Point a copy of `.env` at it, then `php artisan migrate --seed`.

- [ ] **Step 2: Verify ogr2ogr is reachable**

```bash
ogr2ogr --version
```

If this fails, install GDAL and set `IMPORT_OGR2OGR_PATH` to the binary's full path.

- [ ] **Step 3: Import the geodatabase through the dashboard**

Sign in as a `super_admin`, open **/imports**, choose **قاعدة بيانات جغرافية**, and upload `GDB.zip`.

Expected preview: **168 total items, 166 parcels, 165 deeds** — 166 to create, 0 to update on a first run. Confirm, then check:

```bash
psql -U postgres -d sakuki_import_check -c "
SELECT (SELECT COUNT(*) FROM parcels) AS parcels,
       (SELECT COUNT(*) FROM deeds) AS deeds,
       (SELECT COUNT(*) FROM owners) AS owners,
       (SELECT COUNT(*) FROM parcel_boundaries WHERE measured_area IS NOT NULL) AS with_measured_area;"
```

Expected: `parcels=166`, `deeds=168`, `owners=86`, `with_measured_area=166`.

Note `deeds=168` against 165 distinct numbers — three deed numbers legitimately sit on two parcels each, so they produce two rows.

- [ ] **Step 4: Import both document archives**

Upload `الصكوك.zip`. Expected preview: rule **صك**, 27 items, 0 unmatched. Confirm.

Upload `623_pages_separate.zip`. Expected preview: rule **كروكي مساحي**, 31 items, 0 unmatched. Confirm.

```bash
psql -U postgres -d sakuki_import_check -c "
SELECT photo_type, COUNT(*) FROM parcel_photos GROUP BY photo_type;"
```

Expected: `صك = 27`, `كروكي مساحي = 31`.

- [ ] **Step 5: Verify idempotency**

Re-upload `GDB.zip`. Expected preview: **0 to create, 166 to update**. Confirm, then re-run the count query from Step 3 — every number must be unchanged.

- [ ] **Step 6: Write the runbook**

`docs/import-runbook.md` records, for whoever runs this next: the GDAL requirement and how to point at it, that RAR must be repacked as ZIP, the expected counts above as a regression baseline, how to enable `IMPORT_QUEUE_SYNC` on a host with no queue worker, and how to read a failed batch's `error_message`.

- [ ] **Step 7: Commit**

```bash
git add docs/import-runbook.md
git commit -m "docs(import): add the import runbook with verified expected counts"
```

---

## Notes for the executor

- **Task 3 is the risky one.** The moved code is production logic that already handles real data correctly. Move it verbatim; the only intended changes are the three listed. If a test fails after the move, suspect the move before suspecting the original.
- **`analyze()` must never write.** If you find yourself wanting a write inside an analyze path, the design is wrong — stop and raise it.
- **Do not add RAR support.** PHP has no `rar` extension on this host and adding a shell-out to `unrar` would repeat the GDAL dependency problem for a format the client can trivially repack.
- **The GDB's field name `S_DIM` is uppercase** where its siblings are `N_Dim`/`E_Dim`/`W_Dim`. That is the source data's inconsistency, and the existing code already handles it. Do not normalise it.
