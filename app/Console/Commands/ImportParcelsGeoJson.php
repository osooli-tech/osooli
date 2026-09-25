<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\Spatial;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports parcels from a GeoJSON file (EPSG:4326) exported from the survey GDB.
 *
 * Mirrors database/import/gdb_import.py, but runs through Laravel so it works on
 * hosts without Python/GDAL, against whichever database is primary.
 */
class ImportParcelsGeoJson extends Command
{
    protected $signature = 'app:import-parcels-geojson
                            {--file=import/sakoki_with_deed.geojson : GeoJSON path relative to storage/app}';

    protected $description = 'Import parcels, deeds, owners, boundaries and survey decisions from a GeoJSON export';

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

    public function handle(): int
    {
        $path = storage_path('app/'.$this->option('file'));

        if (! is_file($path)) {
            $this->error("GeoJSON file not found: {$path}");

            return self::FAILURE;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        $features = $raw['features'] ?? null;

        if (! is_array($features) || $features === []) {
            $this->error('No features found in the GeoJSON file.');

            return self::FAILURE;
        }

        $this->info('Features: '.count($features));

        $startedAt = now();
        $officeId = $this->engineeringOfficeId();
        $cityId = $this->cityId();

        // A parcel held by several owners appears as one feature per owner,
        // sharing the same Geo_ID + Deed_No.
        $groups = [];
        foreach ($features as $feature) {
            $p = $feature['properties'] ?? [];
            $key = ($p['Geo_ID'] ?? '').'|'.($p['Deed_No'] ?? '');
            $groups[$key][] = $feature;
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'deeds' => 0, 'owners' => 0, 'boundaries' => 0, 'decisions' => 0, 'errors' => 0];
        $bar = $this->output->createProgressBar(count($groups));
        $bar->start();

        foreach ($groups as $group) {
            try {
                // Regular closure with `use (&$stats)` — an arrow fn would capture
                // $stats by value and silently drop the counters.
                DB::transaction(function () use ($group, $cityId, $officeId, &$stats): void {
                    $this->importGroup($group, $cityId, $officeId, $stats);
                });
            } catch (Throwable $e) {
                $stats['errors']++;
                $geoId = $group[0]['properties']['Geo_ID'] ?? '?';
                $this->newLine();
                $this->warn("✗ {$geoId}: ".$e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->logSync($startedAt, $stats, basename($path));

        $this->table(
            ['قطع جديدة', 'قطع محدّثة', 'صكوك', 'ملاك جدد', 'حدود', 'قرارات', 'أخطاء'],
            [[$stats['inserted'], $stats['updated'], $stats['deeds'], $stats['owners'], $stats['boundaries'], $stats['decisions'], $stats['errors']]]
        );

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
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
        $boundary = DB::table('parcel_boundaries')->where('parcel_id', $parcelId)->first(['id', 'engineering_office_id']);

        if ($boundary === null) {
            DB::table('parcel_boundaries')->insert($borders + [
                'parcel_id' => $parcelId,
                'measured_area' => null,
                'engineering_office_id' => $officeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('parcel_boundaries')->where('id', $boundary->id)->update($borders + [
                'engineering_office_id' => $boundary->engineering_office_id ?? $officeId,
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

    /** @param  array<string, int>  $stats */
    private function logSync(Carbon $startedAt, array $stats, string $source): void
    {
        DB::statement(
            'INSERT INTO sync_log (sync_started_at, sync_finished_at, records_imported, records_updated, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $startedAt,
                now(),
                $stats['inserted'],
                $stats['updated'],
                $stats['errors'] === 0 ? 'success' : 'partial',
                sprintf(
                    'src=%s | new=%d upd=%d deeds=%d owners=%d err=%d',
                    $source, $stats['inserted'], $stats['updated'], $stats['deeds'], $stats['owners'], $stats['errors']
                ),
            ]
        );
    }
}
