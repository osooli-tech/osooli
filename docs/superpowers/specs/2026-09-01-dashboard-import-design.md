# Dashboard Data Import — Design

**Date:** 2026-09-01
**Status:** Approved by user

## Goal

Let an administrator load survey data into the system from the dashboard
instead of the terminal: upload a zipped ESRI File Geodatabase to create or
update parcels, deeds, owners, boundaries and survey decisions; upload a ZIP of
PDFs to attach deed documents and survey maps to the parcels they belong to.

Every upload is analysed first and written only after the administrator
confirms what the analysis found.

## Source data

The design is built against three archives supplied by the client, all verified
against each other before this spec was written.

| Archive | Contents | Verified |
| --- | --- | --- |
| `GDB.rar` | `Sakuki.gdb`, layer `sakoki_with_deed`, 168 features, EPSG:32638. Second layer `Adjacent_Parcel`, 1090 polygons. | 166 unique `Geo_ID`, 165 unique `Deed_No`, 86 owners, 25 plans, 3 districts |
| `الصكوك.rar` | 27 PDFs named `<deed_no>.pdf` — Ministry of Justice صك الإفراغ documents | 27/27 match a `Deed_No` in the GDB |
| `623_pages_separate.zip` | 31 PDFs named `<parcel_no> - 623.pdf` — A3 survey maps (كروكي مساحي) | 31/31 are exactly the parcel numbers of `Plan_No=623` |

Four properties of this data drive decisions below:

- **A parcel can carry two deeds.** `Geo_ID` `28-112` and `34-82` each appear
  twice with different deed numbers and different owners — a transfer, not
  co-ownership. The importer's existing `Geo_ID|Deed_No` grouping already
  handles this correctly.
- **A deed can span two parcels.** `911605004832`, `996426000780` and
  `896426000682` each appear on two `Geo_ID`s. This breaks the current document
  linker (see Decisions).
- **Plan numbers are not numeric.** Plan `20A` exists, so no matching rule may
  assume digits.
- **Coded domains are numeric strings.** `Deed_Status`, `Deed_Class`, `Qrar`
  and friends arrive as `"1"`, `"2"`, `"4"`, matching the existing
  `ENUM_VALUES` position mapping. No change needed there.

## Decisions

- **Delivery:** a dashboard upload page, not a CLI-only script. The existing
  artisan commands remain and are refactored to call the same services, so the
  two surfaces cannot drift.
- **GDB conversion:** the server converts. PHP cannot read a File Geodatabase
  and no library exists, so `GdbConverter` shells out to `ogr2ogr`. This makes
  GDAL a hard production dependency — accepted by the user with the mitigation
  that a missing binary produces an explicit error naming it, and that a plain
  `.geojson` is also accepted as an escape hatch.
- **Large files:** chunked upload from the browser. The 623 archive is 77 MB
  against a server limit of `upload_max_filesize=2M` / `post_max_size=8M`.
  Chunking at 2 MB fits inside the existing limits, so no server configuration
  change is required.
- **No client-side hashing.** `SubtleCrypto` has no streaming digest, so
  hashing 77 MB in the browser would mean holding it all in memory for no gain.
  The client declares the size; the server verifies the assembled size and
  computes the sha256 itself.
- **Preview before write:** `analyze()` reads and reports, `commit()` writes.
  Only a batch in the `previewed` state may be committed.
- **Document detection is inferred, then confirmed** — the page proposes the
  rule it detected and shows the match counts; the administrator confirms.
- **Extra GDB data:** `Survey_Area` now populates
  `parcel_boundaries.measured_area`, which the current importer always leaves
  NULL. The `_2` boundary fields (`matches_deed`) and the `Adjacent_Parcel`
  layer are explicitly **out of scope** for this work.

## Data model

New table `import_batches`, one row per upload attempt:

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint | |
| `uuid` | uuid, unique | public identifier used in chunk URLs |
| `user_id` | FK users, restrictOnDelete | who uploaded — the audit trail |
| `kind` | string | `gdb` \| `documents`, cast to `ImportKind` |
| `status` | string | lifecycle below, cast to `ImportStatus` |
| `original_filename` | string | |
| `byte_size` | bigint | size the client declared |
| `received_chunks` | integer, default 0 | next expected chunk index |
| `checksum` | string, nullable | sha256, computed server-side on assembly |
| `stored_path` | string, nullable | assembled file on the private disk |
| `preview` | jsonb, nullable | what the dry run found |
| `result` | jsonb, nullable | what the commit did |
| `error_message` | text, nullable | populated on failure |
| `analyzed_at`, `committed_at` | timestamps, nullable | |

`kind` and `status` are string columns cast to PHP enums rather than native
Postgres enum types. The repo reserves native enums for values that come from
the ArcGIS coded domains (`deed_status`, `asset_type`, …) — those are source
data with a fixed vocabulary. Import lifecycle is application state that
changes when the code changes, and a native enum needs a migration to alter.
The migration carries a comment saying so.

## Lifecycle

```
uploading → uploaded → analyzing → previewed → committing → completed
                           │            │            │
                           └────────────┴────────────┴──→ failed
```

Forward-only. The guard that matters: **only a `previewed` batch can be
committed**, enforced both in the Livewire action and inside
`CommitImportBatch` itself, so a double-clicked Confirm or a replayed request
cannot write twice and nothing can commit without having been analysed.

## Components

### The importer seam

```php
interface Importer
{
    public function analyze(string $sourcePath): ImportPreview;   // reads only
    public function commit(string $sourcePath): ImportResult;     // writes
}
```

`analyze()` is contractually forbidden from writing. This is what makes
preview-then-confirm trustworthy and it is directly testable: the tests assert
every relevant row count is unchanged after an analyze.

`ImportPreview` and `ImportResult` are readonly value objects with typed
counters, a `warnings` list and `toArray()` for the jsonb columns.
`ImporterFactory` resolves the implementation from `ImportKind`.

### Services (`app/Services/Import/`)

| Class | Responsibility | Depends on |
| --- | --- | --- |
| `ArchiveExtractor` | safe unzip — zip-slip path guard, entry-count and total-size caps | `ZipArchive` |
| `GdbConverter` | probe `ogr2ogr`, locate `*.gdb`, pick the layer, convert to EPSG:4326 GeoJSON | `ArchiveExtractor` |
| `ParcelGeoJsonImporter` | the logic lifted from `ImportParcelsGeoJson`, behaviour unchanged, plus `Survey_Area` → `measured_area` | — |
| `GdbImporter` | `Importer` for `kind=gdb` | Converter + ParcelGeoJsonImporter |
| `DocumentImporter` | `Importer` for `kind=documents` — filename rules, parcel matching, file placement | `ArchiveExtractor` |

`GdbConverter` selects the layer carrying both `Geo_ID` and `Deed_No`. If no
layer qualifies it fails and lists the layers it did find, which is what
distinguishes a wrong archive from a broken one.

### Jobs

`AnalyzeImportBatch` and `CommitImportBatch`, queued on the existing `database`
connection. An `IMPORT_QUEUE_SYNC` config flag dispatches them inline instead —
without a running worker the page would otherwise sit at "analyzing" forever,
and a mystery hang is a worse failure than a slow request.

### Upload endpoints

1. `POST /imports` — create the batch (kind, filename, size); returns `uuid`
   and chunk size.
2. `POST /imports/{uuid}/chunk` — appends **only if** `index ===
   received_chunks`; otherwise `409` carrying the expected index so a dropped
   connection resumes rather than corrupting the file. Sequential, one request
   in flight.
3. `POST /imports/{uuid}/complete` — verifies the assembled size against
   `byte_size`, computes the checksum, confirms the archive opens, then
   dispatches `AnalyzeImportBatch`.

Chunk size is 2 MB and the total upload is capped at 512 MB — comfortably above
the 77 MB archive on hand without letting a stray file fill the disk. Accepted
extensions: `.zip` or `.geojson` for `kind=gdb`, `.zip` for `kind=documents`.
Staged uploads live on the private `local` disk under `imports/{uuid}/`.

### Document detection

Applied to the filename stem:

| Pattern | Meaning | Type | Matched against |
| --- | --- | --- | --- |
| `^\d{10,14}$` | deed number | `صك` | `deeds.deed_no` |
| `^(.+?)\s*-\s*(.+?)$` | parcel + plan | `كروكي مساحي` | `parcels.parcel_no` + `plans.plan_no` |

The rule matching the majority of entries is selected and named in the preview.
Files not matching the selected rule are reported as unmatched, never retried
against the other rule — a silent second guess is how the wrong document type
ends up attached to a parcel. Confirm writes only matched files. PDFs are
placed on the `public` disk under `documents/deeds/` and `documents/surveys/`,
matching what `LinkDeedDocuments` already does.

**Bug fixed here:** `LinkDeedDocuments` matches a deed number with `->first()`,
so when one deed covers two parcels the second silently loses its document.
This GDB contains three such deeds. Both the service and the refactored command
link every parcel carrying the number, and the preview reports the fan-out.

### UI

Route `/imports` inside the existing `auth`/`user.active`/`set.locale` group,
gated on a new `imports.create` permission. `App\Livewire\Imports\ImportWizard`
drives: choose kind and file → upload progress → preview → confirm → result,
polling while a job is in flight. Sidebar entry behind `@can`. Recent batches
are listed with their status, uploader and counts.

Authorization is checked server-side in every Livewire action, not only in the
blade `@can` — Livewire actions are client-callable.

## Changes

1. `database/migrations/` — create `import_batches`.
2. `app/Enums/ImportKind.php`, `app/Enums/ImportStatus.php`.
3. `app/Models/ImportBatch.php` — casts, `user()` relation, state guards.
4. `app/Services/Import/` — `Importer` interface, `ImportPreview`,
   `ImportResult`, `ImporterFactory`, `ArchiveExtractor`, `GdbConverter`,
   `ParcelGeoJsonImporter`, `GdbImporter`, `DocumentImporter`.
5. `app/Jobs/AnalyzeImportBatch.php`, `app/Jobs/CommitImportBatch.php`.
6. `app/Http/Controllers/ImportUploadController.php` — the three endpoints.
7. `app/Livewire/Imports/ImportWizard.php` + blade view + upload JS.
8. `app/Console/Commands/ImportParcelsGeoJson.php` — reduced to a wrapper over
   `ParcelGeoJsonImporter`; behaviour preserved.
9. `app/Console/Commands/LinkDeedDocuments.php` — reduced to a wrapper over
   `DocumentImporter`; multi-parcel deed bug fixed.
10. `app/Console/Commands/PruneImportBatches.php` — drop staged files for
    batches older than the retention window (default 7 days). The batch rows
    are kept as history; only the staged archive is deleted.
11. `database/seeders/RolesAndPermissionsSeeder.php` — add `imports.create`.
    `super_admin` gets it through `null`, `manager` through `except`;
    `engineer`'s explicit `only` list excludes it.
12. `app/Livewire/Settings/RoleManager.php` — new `imports` group.
13. `routes/web.php` — the `/imports` routes.
14. `resources/views/components/sidebar.blade.php` — nav entry.
15. `config/` — `ogr2ogr` path, chunk size, max upload size, retention window,
    `IMPORT_QUEUE_SYNC`.
16. Lang (`ar` + `en`) — `imports.php`, plus `nav.php` for the sidebar label
    and `permissions.php` / `settings.php` entries for the new permission.

## Error handling

Each failure names its own cause rather than surfacing a parse error:

- `ogr2ogr` not found → names the binary and the `IMPORT_OGR2OGR_PATH` override.
- No `.gdb` directory inside the archive.
- No layer carrying `Geo_ID` and `Deed_No` → lists the layers that were found.
- Zip-slip entry, entry-count cap or size cap exceeded → rejected before
  extraction.
- Assembled size does not match the declared size → batch fails, file discarded.
- Chunk arriving out of order → `409` with the expected index.
- Per-feature errors during commit → counted and continued, as the existing
  importer already does, and surfaced in `result`.

A failed batch keeps its staged file and `error_message` for diagnosis until
`PruneImportBatches` removes it.

## Testing

Fixtures are small and committed; the 77 MB archive is not. The GeoJSON fixture
copies real features out of the supplied GDB, deliberately including the
awkward cases: the two parcels carrying two deeds each, the three deeds
spanning two parcels, and a co-owner pair. The document fixture is a ZIP of
dummy PDFs using both naming conventions plus names matching neither.

| Test | Asserts |
| --- | --- |
| analyze is read-only | every relevant row count unchanged after `analyze()` |
| commit is idempotent | running twice produces no duplicate parcels, deeds or owners |
| state guard | only `previewed` commits; a second confirm is refused |
| chunk ordering | out-of-order chunk returns `409`; resume from the reported index succeeds |
| size mismatch | assembled size ≠ declared size fails the batch |
| zip-slip | a traversing entry is rejected before extraction |
| deed fan-out | a deed number on two parcels links a document to both |
| unmatched files | reported in the preview, absent from the database |
| co-ownership | one parcel, one deed, two owners produces two `deed_owners` rows |
| two deeds on a parcel | both deed rows created, neither overwriting the other |
| measured_area | `Survey_Area` lands in `parcel_boundaries.measured_area` |
| authorization | `engineer` receives 403 from the page and from every Livewire action |
| converter | `ogr2ogr` invocation faked in unit tests; one integration test runs the real binary and skips when absent |

## Out of scope

- The `Adjacent_Parcel` layer (1090 polygons) — needs its own table and is only
  useful once the map draws surrounding parcels.
- `parcel_boundaries.matches_deed` from the `_2` boundary fields — 8 of 168
  features diverge, but the comparison rule needs the client's definition of
  "مطابق" before it can be encoded.
- RAR support. PHP has `zip` but not `rar`, so the supplied `.rar` archives
  must be repacked as ZIP before upload. A one-off local conversion is provided
  with the delivery rather than built into the server.
