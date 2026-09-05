# Dashboard Import — Operator Runbook

This is the operational guide for the GDB / document import feature
(`/imports`). It exists so an administrator who was not part of the
implementation can run a real import against the client's data, read the
result, recover from a failure, and keep the server's disk clean afterward.

The application UI is Arabic-first; this runbook is English because it is
for whoever administers the server, not for end users of the dashboard.

---

## 0. Verification status — read this first

**Be clear-eyed about what has and has not been run.** This is not a
"probably works" feature — most of it has never executed against a real
database.

**Genuinely executed and passing, in this environment, as of this
runbook:**

- `vendor/bin/phpunit tests/Unit/Import` — 63/63 tests pass. These are pure
  unit tests (state-machine transitions, value-object serialisation) with no
  database dependency.
- `vendor/bin/phpunit tests/Feature/Import/GdbConverterTest.php` — 7 of 8
  pass, 1 skipped. The skip is the one test that shells out to a real
  `ogr2ogr`/`ogrinfo`; it is written to skip automatically when those
  binaries are absent, which they are on this machine. Nothing here touches
  Postgres.

**Written but never executed, on every task that touches the database
(Tasks 3, 4, 6, 7, 8, 9, 10):**

Every `RefreshDatabase` Feature test in this feature — covering the GDB
parcel importer, the document importer, the `ImportBatch` model and its
state machine against a real table, the analyze/commit queue jobs, the
chunked-upload HTTP controller, the Livewire wizard page, and the pruning
command — was written during development but **has never been run**, because
no Postgres instance with credentials was reachable at any point during
implementation. Confirmed again while preparing this runbook: the same
`SQLSTATE[08006] ... no password supplied` failure still occurs here.

Beyond the test suite: **nobody has opened `/imports` in a browser.** The
wizard's JavaScript (chunked upload, polling, progress bar), its interaction
with real Livewire wire calls, and the full click-through of upload → analyze
→ preview → confirm → commit have zero real-world evidence behind them —
only code review and static analysis (`phpstan`, `pint`).

**What you should run first, before trusting this feature with real
production data:**

1. Point `.env` at a real, reachable Postgres+PostGIS instance (a disposable
   `sakuki_test` database, not production) and run the full suite:
   ```bash
   php artisan test tests/Feature/Import
   ```
   Fix anything that fails. Given the volume of unexecuted code, expect to
   find at least a few real defects — that is the entire point of running it
   before this document's Steps 1–5 below touch real client data.
2. Only after that suite is green, follow Steps 1–5 in this runbook against
   a **staging** copy of the database, not production, the first time.
3. Only after a staging run produces the exact counts documented below,
   repeat against production.

Steps 1–5 in this runbook (the actual import of the client's GDB and
document archives) were **NOT performed** while writing this document. Doing
so requires a reachable Postgres with PostGIS **and** the GDAL `ogr2ogr` /
`ogrinfo` binaries; neither exists in the environment this runbook was
written in (verified directly: connecting to Postgres returns
`SQLSTATE[08006] ... no password supplied`, and `ogr2ogr` / `ogrinfo` are
both absent from `PATH`). The expected-count figures throughout this
document come from measuring the client's actual `Sakuki.gdb` file
(`ogrinfo`/manual inspection of the source geodatabase) and from reading the
import code's upsert logic — not from a completed run. Treat every "expected"
number below as a prediction to confirm, not a fact already established by
this runbook.

---

## 1. Prerequisites

| Requirement | Why | How to check |
|---|---|---|
| PostgreSQL with PostGIS | `parcels.geom` and related geometry columns | `psql -c "SELECT postgis_version();"` |
| GDAL `ogr2ogr` **and** `ogrinfo` | Required together for any `.gdb` upload — see §1.1 | `ogr2ogr --version` and `ogrinfo --version` |
| A queue worker, or `IMPORT_QUEUE_SYNC=true` | Analyze/commit run as jobs | `php artisan queue:work` running, or the env flag set |
| A user with the `imports.create` permission | Every import route is gated on it | See §5 |
| Enough free disk under `storage/app/private/imports/` | Staged uploads land there before conversion | `IMPORT_MAX_UPLOAD_BYTES` (512 MB default) per in-flight upload |

### 1.1 GDAL is a hard requirement for `.gdb` uploads — both binaries

Confirmed by reading `app/Services/Import/GdbConverter.php` and
`app/Services/Import/GdbLayerPicker.php`:

- **`ogr2ogr`** does the actual conversion of the File Geodatabase to
  GeoJSON in EPSG:4326 (`GdbConverter::convert()`). Its path is
  `config('imports.ogr2ogr_path')`, i.e. env var **`IMPORT_OGR2OGR_PATH`**
  (default: `ogr2ogr`, resolved from `PATH`).
- **`ogrinfo`** is used *before* conversion to enumerate the geodatabase's
  layers and pick the one that carries both a `Geo_ID` and a `Deed_No`
  field (`GdbLayerPicker::pick()`). This matters because a real GDB can
  contain more than one layer — see §4. Its path is
  `config('imports.ogrinfo_path')`, i.e. env var **`IMPORT_OGRINFO_PATH`**
  (default: `ogrinfo`).
- Both binaries ship in the same GDAL package, so installing GDAL normally
  satisfies both at once. `GdbConverter::isAvailable()` is checked *before*
  the archive is even extracted, specifically so an operator on a host
  without GDAL is told immediately rather than after a slow extraction of a
  possibly very large archive.
- The layer picker **never guesses**. If it cannot enumerate layers via
  `ogrinfo -al -so -json` (falling back to plain-text `-al -so` for GDAL
  builds older than 3.7) or cannot find a layer with both required fields,
  it throws with the actual `ogrinfo` diagnostic output rather than silently
  converting the wrong layer.

### 1.2 The `.geojson` escape hatch (no GDAL needed)

A plain `.geojson` (or `.json`) upload bypasses GDAL entirely —
`GdbConverter::convert()` returns the source path unchanged when the
filename ends in `.geojson`/`.json`. This is the documented path for a host
that cannot or will not install GDAL: export the geodatabase to GeoJSON
elsewhere (e.g. on a machine that has GDAL, or via ArcGIS/QGIS export) and
upload that file instead of the `.gdb`. Everything downstream — the parcel
importer, the preview, the commit — behaves identically regardless of which
path produced the GeoJSON.

### 1.3 RAR is never supported — repack as ZIP first

Confirmed: PHP's `rar` extension is not loaded in this environment (`php -m`
shows no `rar` module), and nothing in the codebase references it —
`ImportUploadController::create()` whitelists only `zip` (plus `geojson`/
`json` for the GDB kind) as accepted extensions. **A `.rar` upload will be
rejected outright** with the `imports.errors.extension` message; there is no
server-side unpacking fallback for it.

If the client's original archives are `.rar` (as they are — `GDB.rar` and
`الصكوك.rar`), they must be repacked as `.zip` before uploading. This has
already been done for the files referenced in this runbook:
`C:\Users\abdo\Downloads\GDB.zip` and `C:\Users\abdo\Downloads\الصكوك.zip`
both already exist as ZIP repacks of the original RAR archives. Only the
`.zip` versions are uploadable.

---

## 2. Environment configuration reference

All keys live in `config/imports.php`, each reading an env var:

| Env var | Config key | Default | Notes |
|---|---|---|---|
| `IMPORT_OGR2OGR_PATH` | `ogr2ogr_path` | `ogr2ogr` | Full path if not on `PATH` |
| `IMPORT_OGRINFO_PATH` | `ogrinfo_path` | `ogrinfo` | Full path if not on `PATH` |
| `IMPORT_CHUNK_BYTES` | `chunk_bytes` | `2 * 1024 * 1024` (2 MB) | Per-chunk upload size — see §2.1 |
| `IMPORT_MAX_UPLOAD_BYTES` | `max_upload_bytes` | `512 * 1024 * 1024` (512 MB) | Whole-file cap, enforced server-side |
| `IMPORT_MAX_ARCHIVE_ENTRIES` | `max_archive_entries` | `5000` | Zip-bomb guard on entry count |
| `IMPORT_MAX_ARCHIVE_BYTES` | `max_archive_bytes` | `2 * 1024 * 1024 * 1024` (2 GB) | Zip-bomb guard on extracted size |
| `IMPORT_RETENTION_DAYS` | `retention_days` | `7` | Days a staged archive survives before pruning — see §7 |
| `IMPORT_QUEUE_SYNC` | `queue_sync` | `false` | Run analyze/commit inline — see §2.2 |
| `DB_QUEUE_RETRY_AFTER` | (Laravel queue config, not `imports.php`) | should be `1900` in `.env.example` | Must exceed the longest job timeout — see §2.3 |

### 2.1 Why chunking avoids a `upload_max_filesize` change

Uploads are sent to `ImportUploadController::chunk()` in `IMPORT_CHUNK_BYTES`
pieces (2 MB by default), not as one HTTP request carrying the whole
archive. Because no single request ever exceeds a few megabytes, this
sidesteps PHP's `upload_max_filesize`/`post_max_size` ini limits entirely —
a host can accept the client's ~600 MB combined archives without touching
`php.ini`. The only real ceiling is `IMPORT_MAX_UPLOAD_BYTES`, checked
server-side against the declared `byte_size` at batch creation and again
against the actual assembled file size at `complete()`.

### 2.2 `IMPORT_QUEUE_SYNC=true` — for hosts with no queue worker

Setting this makes analyze and commit run **inline**, synchronously, inside
the HTTP request that triggers them — for a host that has no `queue:work`
process running. Read directly from `config/imports.php`'s own comment:

> Note: the jobs' `$timeout` property is NOT enforced in this mode — Laravel's
> `SyncQueue` has no timeout machinery at all (that is Laravel's design, not
> a bug here), so the real ceiling becomes PHP's `max_execution_time` and the
> web server's gateway timeout. Intended for hosts without a queue worker and
> for modest-sized imports; a host that expects large imports should run a
> real queue worker instead.

Given the client's `623_pages_separate.zip` is ~77 MB across 31 PDFs and the
GDB conversion itself can take real wall-clock time, **prefer a real queue
worker** (`php artisan queue:work`) over `IMPORT_QUEUE_SYNC=true` for
anything beyond a quick smoke test. If you do use sync mode, raise PHP's
`max_execution_time` and your reverse proxy's request timeout accordingly,
since nothing in the application enforces a ceiling for you.

### 2.3 `DB_QUEUE_RETRY_AFTER` must exceed both job timeouts

`AnalyzeImportBatch::$timeout = 900` (15 minutes) and
`CommitImportBatch::$timeout = 1800` (30 minutes) — read directly from
`app/Jobs/AnalyzeImportBatch.php` and `app/Jobs/CommitImportBatch.php`.
Laravel's documented requirement is that a queue connection's `retry_after`
must exceed the longest-running job's timeout, or the queue worker will
consider a still-running job abandoned and hand a second worker the same
job — for `CommitImportBatch`, that means two workers writing the same
import at once.

`.env.example` already sets `DB_QUEUE_RETRY_AFTER=1900`, 100 seconds above
the 1800-second commit timeout. If you change the queue connection or copy
`.env.example` into a fresh `.env`, **do not drop this value back to the
Laravel default of 90** — that default is far below both job timeouts and
would make duplicate delivery of a genuinely still-running import routine
rather than exceptional.

---

## 3. Required permission and which roles have it

Every import route (`/imports`, upload create/chunk/complete, and the
wizard's confirm action) is gated on the **`imports.create`** permission —
confirmed in `routes/web.php` (`Route::middleware('can:imports.create')`)
and `ImportUploadController::create()`/`complete()`
(`$this->authorize('imports.create')`).

Reading `database/seeders/RolesAndPermissionsSeeder.php` directly:

| Role | Has `imports.create`? | Why |
|---|---|---|
| `super_admin` | **Yes** | Gets every permission in the list unconditionally |
| `manager` | **Yes** | Gets every permission *except* `roles.manage`; `imports.create` is not excluded |
| `engineer` | **No** | Explicitly scoped to an `only` list (`parcels.view`, `parcels.view_map`, `documents.download`, `exports.create`, `modification_requests.view`) that does not include it |

If the operator account you plan to use is not `super_admin` or `manager`,
grant the permission first (`php artisan tinker` →
`$user->givePermissionTo('imports.create')`, or assign one of those two
roles) before attempting any of the steps below — otherwise every request
will fail with a 403/404 before reaching the import logic at all.

---

## 4. Step 1 — Import the parcels GDB

**Source file:** `C:\Users\abdo\Downloads\GDB.zip` (repacked from the
client's original `GDB.rar`, which contains `Sakuki.gdb`).

1. Log in as a user with `imports.create` (see §3) and navigate to
   `/imports`.
2. Choose the **parcels / GDB** import kind and select `GDB.zip`.
3. Wait for the chunked upload to complete, then let analysis run
   (automatically, via the queue — or inline if `IMPORT_QUEUE_SYNC=true`).

### 4.1 What the layer picker will do

`Sakuki.gdb` contains **two layers**:

- `sakoki_with_deed` — the parcel layer, 168 features, carrying both
  `Geo_ID` and `Deed_No`. This is the one `GdbLayerPicker` will select and
  the one that gets imported.
- `Adjacent_Parcel` — 1090 neighbouring polygons with neither field. This
  layer is **deliberately not imported** — it has no `Geo_ID`/`Deed_No`, so
  the layer picker skips it by construction, not by a hardcoded name.

If a future GDB export from this client ever renames `sakoki_with_deed` or
restructures its fields, the picker will fail loudly (`ArchiveException`
naming every layer it found) rather than silently importing the wrong one
— if that happens, this is diagnostic information, not something to route
around.

### 4.2 Expected preview

Measured directly from the source geodatabase (168 features in
`sakoki_with_deed`, 166 unique `Geo_ID`s, 165 unique `Deed_No`s, 86 unique
owner national IDs, 25 plans, 3 districts — العمارية/101, الوصيل/21,
الدرعية/46 — no co-ownership anywhere in this dataset). On a **first-ever**
import against an empty parcels table, the preview should read:

- **166 new parcels**, 0 to update — the preview counts distinct `Geo_ID`s
  (parcels), not raw feature/deed groups (fixed in Task 3; see
  `.superpowers/sdd/2026-09-01-dashboard-import/progress.md` ruling F-2).

If the preview instead shows a different "new" count, or any warnings about
missing `Geo_ID`s, stop and investigate before confirming — do not click
through on the assumption the numbers are "close enough."

### 4.3 Confirm, then expected final counts

After confirming and letting the commit job finish, verify these counts
directly in the database:

```sql
SELECT count(*) FROM parcels;                                    -- expect 166
SELECT count(*) FROM deeds;                                      -- expect 168
SELECT count(*) FROM owners;                                     -- expect 86
SELECT count(*) FROM parcel_boundaries WHERE measured_area IS NOT NULL; -- expect 166
```

### 4.4 Why deeds = 168 but there are only 165 distinct deed numbers — this is NOT a bug

This looks like an inconsistency at first glance and is worth explaining
plainly so it is not mistaken for an import defect:

- **`parcels` = 166.** The importer groups source features by
  `Geo_ID`, and writes/upserts one `parcels` row per distinct `Geo_ID`
  (`ON CONFLICT (geo_id) DO UPDATE ...` in
  `ParcelGeoJsonImporter::importGroup()`). There are 166 distinct `Geo_ID`s
  in the source, so there are exactly 166 parcel rows, full stop — even
  though two of those `Geo_ID`s (`28-112` and `34-82`) each appear on two
  separate source features (because each of those two parcels legitimately
  carries two different deeds — this is not co-ownership, it is one parcel
  with two deed records).

- **`deeds` = 168.** The `deeds` table has **no unique constraint on
  `deed_no` alone** — only `foreignId('parcel_id')` plus a plain (non-unique)
  index on `deed_no` (see `database/migrations/..._create_deeds_table.php`).
  The importer's dedup key for a deed is the pair **(`parcel_id`,
  `deed_no`)** — confirmed directly in
  `ParcelGeoJsonImporter::importGroup()`, which does
  `SELECT id FROM deeds WHERE parcel_id = ? AND deed_no IS NOT DISTINCT FROM ?`
  before deciding whether to `INSERT` a new deed row or `UPDATE` an existing
  one. Since the brief confirms **every `(Geo_ID, Deed_No)` pair in the
  source is unique** — there is no duplicate pair anywhere — every one of
  the 168 source features produces its own distinct `(parcel_id, deed_no)`
  key, and therefore its own `deeds` row. Hence 168 deed rows from 168
  features, regardless of how many distinct deed *numbers* that represents.

- **Why only 165 distinct numbers, then.** Three deed numbers
  (`911605004832`, `996426000780`, `896426000682`) each appear on **two
  different parcels** — i.e. the same number, two different
  `(parcel_id, deed_no)` pairs, hence two rows each. That accounts for
  168 − 165 = 3 "extra" rows beyond one-row-per-distinct-number, matching
  exactly. Symmetrically, the 166 → 168 parcel-side gap is explained by the
  two `Geo_ID`s that each carry two deeds (2 "extra" rows). Both
  explanations describe the same 168 total rows from two different angles;
  they are consistent with each other, not two competing stories.

If a re-run of the import ever produces `deeds` ≠ 168 against this same
source file, that is the number to investigate — not 165, and not "close to
166".

---

## 5. Step 2 — Import the document archives

Two separate uploads, both under the **documents** import kind. Each is
matched against parcels/deeds already imported in Step 1 — run Step 1
first.

### 5.1 `الصكوك.zip` (deed scans)

**Source file:** `C:\Users\abdo\Downloads\الصكوك.zip` (repacked from
`الصكوك.rar`) — 27 PDFs, each named by its deed number
(e.g. `911605004832.pdf`).

Upload it via `/imports` under the documents kind. Expected preview:

- **Rule detected: صك (Deed)** — `DocumentImporter` infers the naming rule
  from the majority of filenames; a bare 10–14 digit filename matches the
  `Deed` rule (`DocumentRule::Deed`, regex `^\d{10,14}$`), which the UI
  displays via `documents.photo_types.صك` (confirmed in
  `resources/views/livewire/imports/import-wizard.blade.php` and
  `app/Enums/PhotoType.php`, where `PhotoType::Deed = 'صك'`).
- **27 items, 0 unmatched.**

After confirming, verify:

```sql
SELECT count(*) FROM parcel_photos WHERE photo_type = 'صك';  -- expect 27
```

### 5.2 `623_pages_separate.zip` (boundary survey scans)

**Source file:** `C:\Users\abdo\Downloads\623_pages_separate.zip` (no RAR
counterpart existed for this one — it was already a ZIP) — 31 PDFs, each
named `<parcel> - 623.pdf`.

Upload it the same way. Expected preview:

- **Rule detected: كروكي مساحي (Boundary Survey)** — a filename with a
  `<left> - <right>` shape matches `DocumentRule::SurveyMap`
  (`PhotoType::BoundarySurvey = 'كروكي مساحي'`). Each side of that pattern
  is looked up as `parcels.parcel_no` / `plans.plan_no` respectively — note
  plan numbers are **not always numeric** (plan `20A` exists in this
  dataset), so do not assume a purely numeric plan-number pattern when
  eyeballing results.
- **31 items, 0 unmatched.**

After confirming, verify:

```sql
SELECT count(*) FROM parcel_photos WHERE photo_type = 'كروكي مساحي';  -- expect 31
```

If either upload reports unmatched files, do **not** treat that as
acceptable noise — every filename in both archives is expected to match on
this dataset (0 unmatched is the bar), and a nonzero count means either a
naming inconsistency in the source archive or that Step 1's parcels/deeds
were not fully imported yet.

---

## 6. Idempotency check — re-run the GDB import

Immediately after Step 1 completes, upload the exact same `GDB.zip` again
as a second, independent batch. This is the single most important check in
this runbook, because it proves the upsert logic is safe to re-run — which
matters directly for §7 (a failed commit is recovered by re-running it).

Expected behaviour:

- **Preview: 0 to create, 166 to update.** Every one of the 166 parcels
  already exists by `Geo_ID`, so the second run's preview should show zero
  new parcels and all 166 as updates.
- **After confirming the second run, every count from §4.3 must be
  unchanged**: `parcels` still 166, `deeds` still 168, `owners` still 86,
  `parcel_boundaries` with non-null `measured_area` still 166.

If the second run instead creates new rows, or the counts drift up or down,
the upsert keys (`geo_id` for parcels, `(parcel_id, deed_no)` for deeds,
`national_id` for owners) are not behaving as designed — stop and
investigate before relying on this feature for any correction/re-import
workflow, since the entire safety argument in §7 for "re-running a failed
commit is safe" depends on this holding.

---

## 7. Reading and recovering a failed batch

Every import's lifecycle lives in the **`import_batches`** table
(`database/migrations/..._create_import_batches_table.php`). The columns
that matter for diagnosis:

| Column | Meaning |
|---|---|
| `status` | One of the `ImportStatus` enum values — `uploading`, `analyzing`, `previewed`, `committing`, `completed`, `failed` |
| `error_message` | Set only on `failed`; the exception message, plus (for a commit failure specifically) an appended notice about partial writes — see below |
| `preview` / `result` | JSON blobs the wizard renders; `result` is only populated after a successful commit |
| `stored_path` | Where the staged archive lives on disk (`storage/app/private/<stored_path>`), or `null` once pruned |

To inspect a failed batch directly:

```sql
SELECT id, uuid, kind, status, error_message, original_filename, updated_at
FROM import_batches
WHERE status = 'failed'
ORDER BY updated_at DESC;
```

### A failed commit may have already written partial data — and that is fine

Read directly from `app/Jobs/CommitImportBatch.php`:

> Commit is not one transaction: `ParcelGeoJsonImporter` commits per
> feature-group and `DocumentImporter`'s write loop has no per-item
> transaction, so an exception partway through can leave real rows behind
> even though the batch ends up Failed. ... every failure message says
> plainly that partial data may already be committed, and that re-running
> is safe because every write in this pipeline is an idempotent upsert.

In practice: if a batch shows `status = failed` with a commit-stage error,
**do not manually clean up half-written rows** before retrying. The correct
recovery is simply to **re-upload the same source file as a new batch**
(there is no "retry this batch" button — a fresh upload is the retry
mechanism) and let it run again; §6 above is the direct evidence that
re-running against already-imported data converges to the same correct
counts rather than duplicating anything.

A batch can also be found `failed` with **no** exception ever caught in
`handle()` — e.g. the worker process was OOM-killed or hit the queue-level
timeout outside the `try` block. `AnalyzeImportBatch::failed()` and
`CommitImportBatch::failed()` both handle this case explicitly (Laravel
calls `failed()` once a job is deemed permanently failed), writing
`error_message` with a "did not finish running" prefix so this is
distinguishable in the data from an in-application exception. Either way,
the same re-upload recovery applies.

---

## 8. Pruning old staged uploads

Staged archives under `storage/app/private/imports/<uuid>/` are not kept
forever — `app:prune-import-batches` deletes the files (never the
`import_batches` row itself, which remains as history) once a batch is
older than the retention window **and** has not been touched recently
(protects an upload still actively receiving chunks from being reaped
mid-transfer).

```bash
php artisan app:prune-import-batches                # uses IMPORT_RETENTION_DAYS (default 7)
php artisan app:prune-import-batches --days=30       # override for this run only
```

This is already registered as a daily scheduled job in `routes/console.php`
(`Schedule::command('app:prune-import-batches')->daily()->withoutOverlapping()->onOneServer()`),
so on a host with Laravel's scheduler wired into cron
(`* * * * * php artisan schedule:run`), no manual action is needed —
this section is for manually forcing a cleanup or changing the window.

A batch whose staging directory could not actually be deleted (permission
error, file lock) is left with `stored_path` intact rather than being
falsely marked as pruned, specifically so the next scheduled run retries it
instead of losing track of it — the command's own output will `warn()`
with a nonzero failed count when this happens; that warning is the only
signal you get, since the local disk driver is configured not to throw or
log on its own.

---

## 9. Troubleshooting quick reference

| Symptom | Likely cause | Where to look |
|---|---|---|
| Upload rejected immediately with an "extension" error | File is `.rar`, or another unsupported extension | Repack as `.zip` first — §1.3 |
| GDB upload fails with "ogr2ogr binary ... was not found" | GDAL not installed, or `IMPORT_OGR2OGR_PATH` wrong | §1.1; confirm with `ogr2ogr --version` |
| GDB upload fails with "No layer carrying both Geo_ID and Deed_No" | `ogrinfo` could not enumerate layers, or genuinely no matching layer | §4.1; check the listed layer names in the error message |
| Batch stuck in `analyzing`/`committing` indefinitely | No queue worker running, and `IMPORT_QUEUE_SYNC` is `false` | Start `php artisan queue:work`, or set `IMPORT_QUEUE_SYNC=true` for small imports |
| Same import appears to run twice / duplicate-looking activity | `DB_QUEUE_RETRY_AFTER` lower than a job's `$timeout` | §2.3 — confirm `.env` has `DB_QUEUE_RETRY_AFTER=1900` |
| 403/404 immediately on `/imports` | Account lacks `imports.create` | §3 |
| Document upload reports unmatched files | Filenames don't match the deed (`^\d{10,14}$`) or `<left> - <right>` survey-map pattern | Re-check the archive's filenames against §5 |
| `deeds` count doesn't match `parcels` count after a GDB import | **Expected on this dataset** — read §4.4 before assuming a bug | §4.4 |

---

## Appendix: files referenced in this runbook

- `C:\Users\abdo\Downloads\GDB.zip` — repacked from `GDB.rar`; contains
  `Sakuki.gdb`.
- `C:\Users\abdo\Downloads\الصكوك.zip` — repacked from `الصكوك.rar`; 27 deed
  PDFs.
- `C:\Users\abdo\Downloads\623_pages_separate.zip` — 31 boundary-survey
  PDFs, already a ZIP.

Code read directly to write this runbook: `config/imports.php`,
`app/Services/Import/GdbConverter.php`,
`app/Services/Import/GdbLayerPicker.php`,
`app/Services/Import/DocumentRule.php`,
`app/Services/Import/DocumentImporter.php`,
`app/Services/Import/ParcelGeoJsonImporter.php`,
`app/Jobs/AnalyzeImportBatch.php`, `app/Jobs/CommitImportBatch.php`,
`app/Http/Controllers/ImportUploadController.php`,
`app/Console/Commands/PruneImportBatches.php`,
`database/seeders/RolesAndPermissionsSeeder.php`,
`database/migrations/..._create_import_batches_table.php`,
`database/migrations/..._create_deeds_table.php`, `routes/web.php`,
`routes/console.php`, `.env.example`,
`resources/views/livewire/imports/import-wizard.blade.php`.
