<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports parcels, deeds, owners, boundaries and survey decisions from a
 * GeoJSON export of the survey geodatabase (EPSG:4326).
 *
 * Logic moved here from App\Console\Commands\ImportParcelsGeoJson so the
 * artisan command and the dashboard import run exactly the same code.
 * Mirrors database/import/gdb_import.py. Written as find-then-write, with the
 * spatial SQL from App\Support\Database\Spatial, so it runs the same on
 * PostgreSQL and MariaDB — whichever the dashboard makes primary.
 */
final class ParcelGeoJsonImporter implements Importer
{
    /** Engineering office linked to every imported boundary until real data exists. */
    private const DEFAULT_ENGINEERING_OFFICE = 'مكتب الإسناد العالمي للاستشارات الهندسية';

    /** Geographic chain that holds the districts coming from the District field. */
    private const DEFAULT_COUNTRY = 'المملكة العربية السعودية';

    private const DEFAULT_REGION = 'منطقة الرياض';

    private const DEFAULT_CITY = 'الدرعية';

    /**
     * Enum values in order — the numeric code in the source maps to the position
     * (1 = first). Must match the definitions in create_enum_types.
     *
     * Codes are documented in docs/gdb-coded-domains.md (from the GDB's own
     * ArcGIS domains). Keep this in sync with database/import/gdb_import.py.
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

        // A parcel may carry several deeds, so one Geo_ID can span several
        // groups. The preview counts parcels, which is what the wizard reports.
        // Blank Geo_IDs are excluded from every count here because commit()
        // skips them outright — see importFeatures().
        $geoIds = [];
        $deedGroups = 0;
        $blank = 0;

        foreach ($groups as $group) {
            $geoId = (string) ($group[0]['properties']['Geo_ID'] ?? '');

            if ($geoId === '') {
                $blank += count($group);

                continue;
            }

            $geoIds[$geoId] = $geoId;
            $deedGroups++;
        }

        $warnings = [];

        if ($blank > 0) {
            $warnings[] = trans_choice('imports.warnings.no_geo_id', $blank);
        }

        // Read-only: this single lookup is the only query analyze() is allowed
        // to make. One query for the whole batch rather than one per parcel —
        // the real export runs to ~3,000 parcels and analyze() is called from a
        // queued job.
        $existing = [];
        foreach (DB::table('parcels')->whereIn('geo_id', array_values($geoIds))->pluck('geo_id') as $value) {
            $existing[(string) $value] = true;
        }

        $update = 0;
        foreach ($geoIds as $geoId) {
            if (isset($existing[$geoId])) {
                $update++;
            }
        }

        return new ImportPreview(
            totalItems: count($features),
            willCreate: count($geoIds) - $update,
            willUpdate: $update,
            // $blank is already computed above and importFeatures() reports
            // the exact same features as `skipped` once it runs — hardcoding
            // 0 here (I2 in the final review) let the confirm screen promise
            // "0 unmatched" for a batch the result screen would immediately
            // reveal had skipped some, even though the runbook tells the
            // operator to stop and investigate on exactly this warning.
            unmatched: $blank,
            details: [
                'parcels' => count($geoIds),
                'deeds' => $deedGroups,
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
        $skipped = 0;

        // geo_id => whether the parcel was inserted (true) or updated (false) on
        // the first group of that parcel to land this run. A parcel holding two
        // deeds arrives as two groups; without this the second group would count
        // the row the first one just inserted as an update, and `updated` would
        // not reconcile with the preview's willUpdate.
        $outcomes = [];

        foreach ($groups as $group) {
            $geoId = (string) ($group[0]['properties']['Geo_ID'] ?? '');

            // parcels.geo_id is NOT NULL UNIQUE, so '' is a perfectly legal
            // value: importing a blank one would create a real parcel that every
            // later blank-Geo_ID feature then finds by that same geo_id and
            // writes over, collapsing unrelated parcels into one shared row.
            // Skip instead — the preview already promises this.
            if ($geoId === '') {
                $skipped += count($group);

                continue;
            }

            $insertedBefore = $stats['inserted'];

            try {
                // Regular closure with `use (&$stats)` — an arrow fn would capture
                // $stats by value and silently drop the counters.
                DB::transaction(function () use ($group, $cityId, $officeId, &$stats): void {
                    $this->importGroup($group, $cityId, $officeId, $stats);
                });

                // Recorded only on success, so a rolled-back group cannot leave
                // a phantom parcel in the created/updated tallies.
                $outcomes[$geoId] ??= $stats['inserted'] > $insertedBefore;
            } catch (Throwable $e) {
                $stats['errors']++;
                $warnings[] = $geoId.': '.$e->getMessage();
            }
        }

        if ($skipped > 0) {
            $warnings[] = trans_choice('imports.warnings.no_geo_id', $skipped);
        }

        $created = count(array_filter($outcomes));

        return new ImportResult(
            created: $created,
            updated: count($outcomes) - $created,
            skipped: $skipped,
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

    /**
     * @param  list<array<string, mixed>>  $group
     * @param  array<string, int>  $stats
     */
    private function importGroup(array $group, ?int $cityId, int $officeId, array &$stats): void
    {
        $lead = $group[0];
        $p = $lead['properties'];
        $geoId = (string) $p['Geo_ID'];
        $deedNo = $this->str($p['Deed_No'] ?? null);

        // District → Plan
        $districtId = $this->districtId($this->str($p['District'] ?? null), $cityId);
        $planId = $this->planId((string) $p['Plan_No'], $districtId);

        /*
         * Archiving is deliberately invisible to this importer. The query
         * builder never sees the SoftDeletes scope, so the lookup by geo_id
         * still finds an archived parcel and updates it in place — which is
         * what we want: filtering archived rows out here would make the insert
         * collide with the unique geo_id instead, and the import would fail on
         * a row it can plainly see.
         *
         * Note what it does NOT do: an archived parcel stays archived even when
         * the source file still contains it. Archiving is a decision a person
         * made, and a nightly import should not quietly undo it. Restoring is
         * done from the archive screen, on purpose, by someone accountable.
         *
         * Written as find-then-write rather than a database upsert so it runs
         * the same on PostgreSQL and MariaDB; each group is one transaction.
         */
        [$parcelId, $isNew] = $this->upsert('parcels', ['geo_id' => $geoId], [
            'parcel_no' => $this->str($p['Parcel'] ?? null),
            'plan_id' => $planId,
            'm_price' => $this->num($p['M_price'] ?? null),
            'parcel_price' => $this->num($p['Parcel_price'] ?? null),
            'asset_type' => $this->enum('asset_type', $p['Owner_Type'] ?? null),
            'land_transaction' => $this->enum('land_transaction', $p['Land_Trasaction'] ?? null),
            'allocation_method' => $this->enum('allocation_method', $p['Allocation_Method'] ?? null),
            'fall_in' => $this->enum('fall_in', $p['Fall_In'] ?? null),
        ]);
        $isNew ? $stats['inserted']++ : $stats['updated']++;

        // Geometry — always stored as MultiPolygon
        DB::update(
            'UPDATE parcels SET geom = '.Spatial::fromGeoJson().' WHERE id = ?',
            [Spatial::multiPolygonJson($lead['geometry']), $parcelId]
        );

        // Deed
        $deedStatus = $this->enum('deed_status', $p['Deed_Status'] ?? null);
        $deedClass = $this->enum('deed_class', $p['Deed_Class'] ?? null);
        $existingDeedId = DB::table('deeds')->where('parcel_id', $parcelId)->where('deed_no', $deedNo)->value('id');

        if ($existingDeedId === null) {
            $deedId = (int) DB::table('deeds')->insertGetId([
                'parcel_id' => $parcelId,
                'deed_no' => $deedNo,
                'deed_date_hijri' => $this->hijri($p['Deed_Date'] ?? null),
                'deed_area' => $this->num($p['Area'] ?? null),
                'deed_status' => $deedStatus,
                'deed_class' => $deedClass,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stats['deeds']++;
        } else {
            $deedId = (int) $existingDeedId;
            DB::table('deeds')->where('id', $deedId)->update([
                'deed_status' => $deedStatus,
                'deed_class' => $deedClass,
                'updated_at' => now(),
            ]);
        }

        // Owners (one feature per co-owner)
        foreach ($group as $feature) {
            $fp = $feature['properties'];
            $name = $this->str($fp['Name'] ?? null) ?? 'غير معروف';
            $nationalId = $this->str($fp['Woner_ID'] ?? null);

            if ($nationalId !== null) {
                [$ownerId, $ownerIsNew] = $this->upsert('owners', ['national_id' => $nationalId], ['name' => $name]);
            } else {
                $ownerId = (int) DB::table('owners')->insertGetId([
                    'name' => $name,
                    'national_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $ownerIsNew = true;
            }

            if ($ownerIsNew) {
                $stats['owners']++;
            }

            DB::table('deed_owners')->insertOrIgnore([
                'deed_id' => $deedId,
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Boundaries — engineering office set on insert only, never overwrites a manual assignment
        $borders = [
            'n_border' => $this->str($p['N_Border'] ?? null), 's_border' => $this->str($p['S_Border'] ?? null),
            'e_border' => $this->str($p['E_Border'] ?? null), 'w_border' => $this->str($p['W_Border'] ?? null),
            'n_dim' => $this->num($p['N_Dim'] ?? null), 's_dim' => $this->num($p['S_DIM'] ?? null),
            'e_dim' => $this->num($p['E_Dim'] ?? null), 'w_dim' => $this->num($p['W_Dim'] ?? null),
        ];
        $boundary = DB::table('parcel_boundaries')->where('parcel_id', $parcelId)->first(['id', 'engineering_office_id', 'measured_area']);
        // The surveyed area; a feature without one keeps the area on record.
        $surveyArea = $this->num($p['Survey_Area'] ?? null);

        if ($boundary === null) {
            DB::table('parcel_boundaries')->insert($borders + [
                'parcel_id' => $parcelId,
                'measured_area' => $surveyArea,
                'engineering_office_id' => $officeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('parcel_boundaries')->where('id', $boundary->id)->update($borders + [
                'engineering_office_id' => $boundary->engineering_office_id ?? $officeId,
                'measured_area' => $surveyArea ?? $boundary->measured_area,
                'updated_at' => now(),
            ]);
        }
        $stats['boundaries']++;

        // Survey decision — Qrar is the decision source code, not the decision number
        $folder = $this->str($p['Folder'] ?? null);
        if ($folder !== null) {
            $qrarSource = $this->enum('qrar_source', $p['Qrar'] ?? null);
            $reportNo = $this->str($p['Report_No'] ?? null);
            $decision = DB::table('survey_decisions')->where('parcel_id', $parcelId)->first(['id', 'report_no']);

            if ($decision === null) {
                DB::table('survey_decisions')->insert([
                    'parcel_id' => $parcelId,
                    'qrar_no' => null,
                    'report_no' => $reportNo,
                    'qrar_source' => $qrarSource,
                    'folder' => $folder,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $stats['decisions']++;
            } else {
                DB::table('survey_decisions')->where('id', $decision->id)->update([
                    'qrar_source' => $qrarSource,
                    'report_no' => $reportNo ?? $decision->report_no,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function engineeringOfficeId(): int
    {
        return $this->findOrCreate('engineering_offices', ['name' => self::DEFAULT_ENGINEERING_OFFICE]);
    }

    /** Ensures country → region → city and returns the city id. */
    private function cityId(): int
    {
        $countryId = $this->findOrCreate('countries', ['name_ar' => self::DEFAULT_COUNTRY]);

        $regionId = $this->findOrCreate('regions', ['name_ar' => self::DEFAULT_REGION], ['country_id' => $countryId]);

        return $this->findOrCreate('cities', ['name_ar' => self::DEFAULT_CITY], ['region_id' => $regionId]);
    }

    private function districtId(?string $name, ?int $cityId): ?int
    {
        if ($name === null || $cityId === null) {
            return null;
        }

        return $this->findOrCreate('districts', ['name_ar' => $name, 'city_id' => $cityId]);
    }

    private function planId(string $planNo, ?int $districtId): int
    {
        $planNo = trim($planNo);
        $plan = DB::table('plans')->where('plan_no', $planNo)->first(['id', 'district_id']);

        if ($plan === null) {
            return (int) DB::table('plans')->insertGetId([
                'plan_no' => $planNo,
                'district_id' => $districtId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Keeps a previously linked district instead of nulling it
        DB::table('plans')->where('id', $plan->id)->update([
            'district_id' => $districtId ?? $plan->district_id,
            'updated_at' => now(),
        ]);

        return (int) $plan->id;
    }

    /**
     * The id of the row matching `$match`, inserting it (with `$extra`) first
     * when there is none.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $extra
     */
    private function findOrCreate(string $table, array $match, array $extra = []): int
    {
        $id = DB::table($table)->where($match)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId($match + $extra + ['created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Update the row matching `$match` with `$values`, or insert it.
     *
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $values
     * @return array{0: int, 1: bool} the row id, and whether it was inserted
     */
    private function upsert(string $table, array $match, array $values): array
    {
        $id = DB::table($table)->where($match)->value('id');

        if ($id !== null) {
            DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);

            return [(int) $id, false];
        }

        return [(int) DB::table($table)->insertGetId($match + $values + ['created_at' => now(), 'updated_at' => now()]), true];
    }

    /** Maps a 1-based numeric code to its enum value. */
    private function enum(string $field, mixed $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $index = (int) (float) $code;
        $values = self::ENUM_VALUES[$field] ?? [];

        return $values[$index - 1] ?? null;
    }

    /** Hijri dates are plain text 'YYYY-MM-DD' — never calendar-converted. */
    private function hijri(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function str(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function num(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }
}
