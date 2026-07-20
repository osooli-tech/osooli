<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports parcels from a GeoJSON file (EPSG:4326) exported from the survey GDB.
 *
 * Mirrors database/import/gdb_import.py, but runs through Laravel so it works on
 * hosts without Python/GDAL and reads the database credentials from .env.
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

        // Parcel
        $parcelRow = DB::selectOne(
            'INSERT INTO parcels (parcel_no, geo_id, plan_id, m_price, parcel_price,
                                  asset_type, land_transaction, allocation_method, fall_in,
                                  created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?::asset_type_enum, ?::land_transaction_enum,
                     ?::allocation_method_enum, ?::fall_in_enum, NOW(), NOW())
             ON CONFLICT (geo_id) DO UPDATE SET
                 parcel_no         = EXCLUDED.parcel_no,
                 plan_id           = EXCLUDED.plan_id,
                 m_price           = EXCLUDED.m_price,
                 parcel_price      = EXCLUDED.parcel_price,
                 asset_type        = EXCLUDED.asset_type,
                 land_transaction  = EXCLUDED.land_transaction,
                 allocation_method = EXCLUDED.allocation_method,
                 fall_in           = EXCLUDED.fall_in,
                 updated_at        = NOW()
             RETURNING id, (xmax = 0) AS is_new',
            [
                $this->str($p['Parcel'] ?? null),
                $geoId,
                $planId,
                $this->num($p['M_price'] ?? null),
                $this->num($p['Parcel_price'] ?? null),
                $this->enum('asset_type', $p['Owner_Type'] ?? null),
                $this->enum('land_transaction', $p['Land_Trasaction'] ?? null),
                $this->enum('allocation_method', $p['Allocation_Method'] ?? null),
                $this->enum('fall_in', $p['Fall_In'] ?? null),
            ]
        );

        $parcelId = (int) $parcelRow->id;
        $parcelRow->is_new ? $stats['inserted']++ : $stats['updated']++;

        // Geometry — ST_Multi guarantees MultiPolygon
        DB::update(
            'UPDATE parcels SET geom = ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(?)), 4326) WHERE id = ?',
            [json_encode($lead['geometry']), $parcelId]
        );

        // Deed
        $deedStatus = $this->enum('deed_status', $p['Deed_Status'] ?? null);
        $deedClass = $this->enum('deed_class', $p['Deed_Class'] ?? null);
        $existing = DB::selectOne(
            'SELECT id FROM deeds WHERE parcel_id = ? AND deed_no IS NOT DISTINCT FROM ? LIMIT 1',
            [$parcelId, $deedNo]
        );

        if ($existing === null) {
            $deed = DB::selectOne(
                'INSERT INTO deeds (parcel_id, deed_no, deed_date_hijri, deed_area, deed_status, deed_class, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?::deed_status_enum, ?::deed_class_enum, NOW(), NOW())
                 RETURNING id',
                [$parcelId, $deedNo, $this->hijri($p['Deed_Date'] ?? null), $this->num($p['Area'] ?? null), $deedStatus, $deedClass]
            );
            $deedId = (int) $deed->id;
            $stats['deeds']++;
        } else {
            $deedId = (int) $existing->id;
            DB::update(
                'UPDATE deeds SET deed_status = ?::deed_status_enum, deed_class = ?::deed_class_enum, updated_at = NOW() WHERE id = ?',
                [$deedStatus, $deedClass, $deedId]
            );
        }

        // Owners (one feature per co-owner)
        foreach ($group as $feature) {
            $fp = $feature['properties'];
            $name = $this->str($fp['Name'] ?? null) ?? 'غير معروف';
            $nationalId = $this->str($fp['Woner_ID'] ?? null);

            if ($nationalId !== null) {
                $owner = DB::selectOne(
                    'INSERT INTO owners (name, national_id, created_at, updated_at)
                     VALUES (?, ?, NOW(), NOW())
                     ON CONFLICT (national_id) WHERE national_id IS NOT NULL
                     DO UPDATE SET name = EXCLUDED.name, updated_at = NOW()
                     RETURNING id, (xmax = 0) AS is_new',
                    [$name, $nationalId]
                );
            } else {
                $owner = DB::selectOne(
                    'INSERT INTO owners (name, national_id, created_at, updated_at)
                     VALUES (?, NULL, NOW(), NOW()) RETURNING id, TRUE AS is_new',
                    [$name]
                );
            }

            if ($owner->is_new) {
                $stats['owners']++;
            }

            DB::statement(
                'INSERT INTO deed_owners (deed_id, owner_id, created_at, updated_at)
                 VALUES (?, ?, NOW(), NOW()) ON CONFLICT (deed_id, owner_id) DO NOTHING',
                [$deedId, (int) $owner->id]
            );
        }

        // Boundaries — engineering office set on insert only, never overwrites a manual assignment
        DB::statement(
            'INSERT INTO parcel_boundaries (parcel_id, n_border, s_border, e_border, w_border,
                                            n_dim, s_dim, e_dim, w_dim, measured_area,
                                            engineering_office_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NOW(), NOW())
             ON CONFLICT (parcel_id) DO UPDATE SET
                 n_border = EXCLUDED.n_border, s_border = EXCLUDED.s_border,
                 e_border = EXCLUDED.e_border, w_border = EXCLUDED.w_border,
                 n_dim = EXCLUDED.n_dim, s_dim = EXCLUDED.s_dim,
                 e_dim = EXCLUDED.e_dim, w_dim = EXCLUDED.w_dim,
                 engineering_office_id = COALESCE(parcel_boundaries.engineering_office_id, EXCLUDED.engineering_office_id),
                 updated_at = NOW()',
            [
                $parcelId,
                $this->str($p['N_Border'] ?? null), $this->str($p['S_Border'] ?? null),
                $this->str($p['E_Border'] ?? null), $this->str($p['W_Border'] ?? null),
                $this->num($p['N_Dim'] ?? null), $this->num($p['S_DIM'] ?? null),
                $this->num($p['E_Dim'] ?? null), $this->num($p['W_Dim'] ?? null),
                $officeId,
            ]
        );
        $stats['boundaries']++;

        // Survey decision — Qrar is the decision source code, not the decision number
        $folder = $this->str($p['Folder'] ?? null);
        if ($folder !== null) {
            $qrarSource = $this->enum('qrar_source', $p['Qrar'] ?? null);
            $reportNo = $this->str($p['Report_No'] ?? null);
            $decision = DB::selectOne('SELECT id FROM survey_decisions WHERE parcel_id = ? LIMIT 1', [$parcelId]);

            if ($decision === null) {
                DB::statement(
                    'INSERT INTO survey_decisions (parcel_id, qrar_no, report_no, qrar_source, folder, created_at, updated_at)
                     VALUES (?, NULL, ?, ?::qrar_source_enum, ?, NOW(), NOW())',
                    [$parcelId, $reportNo, $qrarSource, $folder]
                );
                $stats['decisions']++;
            } else {
                DB::update(
                    'UPDATE survey_decisions SET qrar_source = ?::qrar_source_enum,
                     report_no = COALESCE(?, report_no), updated_at = NOW() WHERE id = ?',
                    [$qrarSource, $reportNo, (int) $decision->id]
                );
            }
        }
    }

    private function engineeringOfficeId(): int
    {
        $row = DB::selectOne('SELECT id FROM engineering_offices WHERE name = ?', [self::DEFAULT_ENGINEERING_OFFICE]);

        if ($row !== null) {
            return (int) $row->id;
        }

        $created = DB::selectOne(
            'INSERT INTO engineering_offices (name, created_at, updated_at) VALUES (?, NOW(), NOW()) RETURNING id',
            [self::DEFAULT_ENGINEERING_OFFICE]
        );

        return (int) $created->id;
    }

    /** Ensures country → region → city and returns the city id. */
    private function cityId(): int
    {
        $countryId = $this->findOrCreate(
            'SELECT id FROM countries WHERE name_ar = ?',
            [self::DEFAULT_COUNTRY],
            'INSERT INTO countries (name_ar, created_at, updated_at) VALUES (?, NOW(), NOW()) RETURNING id',
            [self::DEFAULT_COUNTRY]
        );

        $regionId = $this->findOrCreate(
            'SELECT id FROM regions WHERE name_ar = ?',
            [self::DEFAULT_REGION],
            'INSERT INTO regions (country_id, name_ar, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) RETURNING id',
            [$countryId, self::DEFAULT_REGION]
        );

        return $this->findOrCreate(
            'SELECT id FROM cities WHERE name_ar = ?',
            [self::DEFAULT_CITY],
            'INSERT INTO cities (region_id, name_ar, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) RETURNING id',
            [$regionId, self::DEFAULT_CITY]
        );
    }

    private function districtId(?string $name, ?int $cityId): ?int
    {
        if ($name === null || $cityId === null) {
            return null;
        }

        return $this->findOrCreate(
            'SELECT id FROM districts WHERE name_ar = ? AND city_id = ?',
            [$name, $cityId],
            'INSERT INTO districts (city_id, name_ar, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) RETURNING id',
            [$cityId, $name]
        );
    }

    private function planId(string $planNo, ?int $districtId): int
    {
        $planNo = trim($planNo);

        // COALESCE keeps a previously linked district instead of nulling it
        DB::statement(
            'INSERT INTO plans (plan_no, district_id, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON CONFLICT (plan_no) DO UPDATE SET
                 district_id = COALESCE(EXCLUDED.district_id, plans.district_id),
                 updated_at = NOW()',
            [$planNo, $districtId]
        );

        return (int) DB::selectOne('SELECT id FROM plans WHERE plan_no = ?', [$planNo])->id;
    }

    /**
     * @param  list<mixed>  $findBindings
     * @param  list<mixed>  $createBindings
     */
    private function findOrCreate(string $findSql, array $findBindings, string $createSql, array $createBindings): int
    {
        $row = DB::selectOne($findSql, $findBindings);

        if ($row !== null) {
            return (int) $row->id;
        }

        return (int) DB::selectOne($createSql, $createBindings)->id;
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
