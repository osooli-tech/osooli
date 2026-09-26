<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Database\Spatial;
use App\Support\DatabaseEnum;
use App\Support\Geo\Locator;
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

    /**
     * Values the last import could not place in a fixed-choice column,
     * field => value => how many times; reported, and left unchanged.
     *
     * @var array<string, array<string, int>>
     */
    private array $unknown = [];

    /**
     * Plans the file puts in a district other than the one on record,
     * plan number => how many parcels; they stay where they are.
     *
     * @var array<string, int>
     */
    private array $planConflicts = [];

    public function analyze(string $sourcePath): ImportPreview
    {
        return $this->previewFeatures($this->readFeatures($sourcePath));
    }

    /** @param  array<string, mixed>  $options  see options() */
    public function commit(string $sourcePath, array $options = []): ImportResult
    {
        return $this->importFeatures($this->readFeatures($sourcePath), $options);
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
     * @param  array<string, mixed>  $options  see options()
     */
    public function importFeatures(array $features, array $options = []): ImportResult
    {
        $groups = $this->groupByParcelAndDeed($features);
        $o = self::options($options);
        if (! $o['legacy'] && $o['office_id'] === null && $o['office_name'] !== '') {
            $o['office_id'] = $this->findOrCreate('engineering_offices', ['name' => $o['office_name']]);
        }
        $this->unknown = [];
        $this->planConflicts = [];

        $stats = ['inserted' => 0, 'updated' => 0, 'deeds' => 0, 'owners' => 0, 'boundaries' => 0, 'decisions' => 0, 'portfolios' => 0, 'located' => 0, 'not_located' => 0, 'errors' => 0];
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
                DB::transaction(function () use ($group, $o, &$stats): void {
                    $this->importGroup($group, $o, $stats);
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

        $planConflicts = $this->takePlanConflicts();
        if ($planConflicts !== []) {
            $warnings[] = __('imports.warnings.plan_elsewhere', [
                'plans' => implode('، ', array_map(static fn (string $plan, int $n): string => "{$plan} ({$n})", array_keys($planConflicts), $planConflicts)),
            ]);
        }

        if ($stats['not_located'] > 0) {
            $warnings[] = __('imports.warnings.not_located', ['count' => $stats['not_located']]);
        }

        foreach ($this->takeUnknown() as $field => $values) {
            $warnings[] = __('imports.warnings.unknown_values', [
                'field' => $field,
                'values' => implode('، ', array_map(static fn (string $v, int $n): string => "{$v} ({$n})", array_keys($values), $values)),
            ]);
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
                'portfolios' => $stats['portfolios'],
                'located' => $stats['located'],
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
     * The defaults, completed with what was chosen on the review screen.
     *
     * No choices at all — the artisan command, an older caller — keeps the
     * behaviour from before the review screen existed: districts are placed
     * under the fixed default city and every boundary gets the default
     * engineering office.
     *
     * @param  array<string, mixed>  $options
     * @return array{legacy: bool, districts: list<array{name: string, district_id: int|null, city_id: int|null, match: string}>,
     *     district_match: string, parcel_districts: array<string, int>, default_city_id: int|null, plan_placeholders: list<string>, borders: string, qrar: string,
     *     folder: string, portfolios: bool, deedless: string, no_plan: string, office_id: int|null, office_name: string}
     */
    public static function options(array $options): array
    {
        $legacy = $options === [];

        $districts = [];
        foreach (is_array($options['districts'] ?? null) ? $options['districts'] : [] as $row) {
            if (is_array($row) && is_string($row['name'] ?? null)) {
                $districts[] = [
                    'name' => $row['name'],
                    'district_id' => is_numeric($row['district_id'] ?? null) ? (int) $row['district_id'] : null,
                    'city_id' => is_numeric($row['city_id'] ?? null) ? (int) $row['city_id'] : null,
                    // '' follows the method chosen for all parcels.
                    'match' => in_array($row['match'] ?? null, ['name', 'map'], true) ? (string) $row['match'] : '',
                    // The name of the district made for parcels whose District is empty.
                    'new_name' => trim((string) ($row['new_name'] ?? '')),
                ];
            }
        }

        $placeholders = $options['plan_placeholders'] ?? ($legacy ? [] : ['بدون']);
        if (is_string($placeholders)) {
            $placeholders = preg_split('/[،,]/u', $placeholders) ?: [];
        }

        $pick = static fn (string $key, array $allowed, string $default): string => in_array($options[$key] ?? null, $allowed, true) ? (string) $options[$key] : $default;

        // Parcels given a district of their own on the review screen.
        $parcelDistricts = [];
        foreach (is_array($options['parcel_districts'] ?? null) ? $options['parcel_districts'] : [] as $exception) {
            if (is_array($exception) && is_string($exception['geo_id'] ?? null) && is_numeric($exception['district_id'] ?? null)) {
                $parcelDistricts[trim($exception['geo_id'])] = (int) $exception['district_id'];
            }
        }

        return [
            'legacy' => $legacy,
            'districts' => $districts,
            'district_match' => $pick('district_match', ['name', 'map'], 'name'),
            'parcel_districts' => $parcelDistricts,
            'default_city_id' => is_numeric($options['default_city_id'] ?? null) ? (int) $options['default_city_id'] : null,
            'plan_placeholders' => array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), (array) $placeholders), static fn (string $v): bool => $v !== '')),
            'borders' => $pick('borders', ['first', 'second', 'prefer_second'], 'first'),
            'qrar' => $pick('qrar', ['number', 'source', 'ignore'], 'source'),
            'folder' => $pick('folder', ['folder', 'ignore'], 'folder'),
            'portfolios' => (bool) ($options['portfolios'] ?? false),
            'deedless' => $pick('deedless', ['placeholder', 'skip'], 'placeholder'),
            // A parcel with no real plan: into its district's stand-in plan, or no plan.
            'no_plan' => $pick('no_plan', ['district_plan', 'none'], $legacy ? 'none' : 'district_plan'),
            'office_id' => is_numeric($options['office_id'] ?? null) ? (int) $options['office_id'] : null,
            // An office not on record yet, named on the review screen.
            'office_name' => mb_substr(trim((string) ($options['office_name'] ?? '')), 0, 150),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $group
     * @param  array<string, mixed>  $o  see options()
     * @param  array<string, int>  $stats
     */
    private function importGroup(array $group, array $o, array &$stats): void
    {
        $lead = $group[0];
        $p = $lead['properties'];
        $geoId = (string) $p['Geo_ID'];
        $deedNo = $this->str($p['Deed_No'] ?? null);

        $districtId = $this->resolveDistrict($geoId, $this->str($p['District'] ?? null), $lead['geometry'] ?? null, $o, $stats);
        $planId = $this->planFor($this->str($p['Plan_No'] ?? null), $districtId, $o);

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
         * A value the file leaves empty never erases the one on record.
         */
        [$parcelId, $isNew] = $this->upsert('parcels', ['geo_id' => $geoId], $this->filled([
            'parcel_no' => $this->str($p['Parcel'] ?? null),
            'plan_id' => $planId,
            'm_price' => $this->num($p['M_price'] ?? null),
            'parcel_price' => $this->num($p['Parcel_price'] ?? null),
            'asset_type' => $this->enum('asset_type', $p['Owner_Type'] ?? null),
            'land_transaction' => $this->enum('land_transaction', $p['Land_Trasaction'] ?? null),
            'allocation_method' => $this->enum('allocation_method', $p['Allocation_Method'] ?? null),
            'fall_in' => $this->enum('fall_in', $p['Fall_In'] ?? null),
        ]));
        $isNew ? $stats['inserted']++ : $stats['updated']++;

        // Geometry — always stored as MultiPolygon
        if (is_array($lead['geometry'] ?? null)) {
            DB::update(
                'UPDATE parcels SET geom = '.Spatial::fromGeoJson().' WHERE id = ?',
                [Spatial::multiPolygonJson($lead['geometry']), $parcelId]
            );
        }

        // Deed — a feature with no deed number either gets a numberless deed
        // to hold its owners, or no deed at all, as chosen.
        $deedId = null;
        if ($deedNo !== null || $o['deedless'] === 'placeholder') {
            $deedFields = $this->filled([
                'deed_date_hijri' => $this->hijri($p['Deed_Date'] ?? null),
                'deed_area' => $this->num($p['Area'] ?? null),
                'deed_status' => $this->enum('deed_status', $p['Deed_Status'] ?? null),
                'deed_class' => $this->enum('deed_class', $p['Deed_Class'] ?? null),
            ]);
            $existingDeedId = DB::table('deeds')->where('parcel_id', $parcelId)->where('deed_no', $deedNo)->value('id');

            if ($existingDeedId === null) {
                $deedId = (int) DB::table('deeds')->insertGetId([
                    'parcel_id' => $parcelId,
                    'deed_no' => $deedNo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ] + $deedFields);
                $stats['deeds']++;
            } else {
                $deedId = (int) $existingDeedId;
                DB::table('deeds')->where('id', $deedId)->update($deedFields + ['updated_at' => now()]);
            }
        }

        // Owners (one feature per co-owner), linked through the deed.
        $portfolio = $o['portfolios'] ? $this->str($p['Real_Estate_portfolio'] ?? null) : null;
        foreach ($deedId === null ? [] : $group as $feature) {
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

            if ($portfolio !== null) {
                $this->placeInPortfolio($ownerId, $parcelId, $portfolio, $stats);
            }
        }

        $this->importBoundary($p, $parcelId, $o, $stats);
        $this->importDecision($p, $parcelId, $o, $stats);
    }

    /**
     * The boundary from the chosen set of border columns: the first
     * (N_Border…), the second (N_Border_2…), or the second where the feature
     * has one and the first otherwise.
     *
     * @param  array<string, mixed>  $p
     * @param  array<string, mixed>  $o
     * @param  array<string, int>  $stats
     */
    private function importBoundary(array $p, int $parcelId, array $o, array &$stats): void
    {
        $first = ['N_Border', 'S_Border', 'E_Border', 'W_Border', 'N_Dim', 'S_DIM', 'E_Dim', 'W_Dim'];
        $second = ['N_Border_2', 'S_Border_2', 'E_Border_2', 'W_Border_2', 'N_Dim_2', 'S_Dim_2', 'E_Dim_2', 'W_Dim_2'];
        $hasSecond = array_filter($second, fn (string $f): bool => $this->str($p[$f] ?? null) !== null) !== [];
        $set = match ($o['borders']) {
            'second' => $second,
            'prefer_second' => $hasSecond ? $second : $first,
            default => $first,
        };

        $values = $this->filled([
            'n_border' => $this->str($p[$set[0]] ?? null), 's_border' => $this->str($p[$set[1]] ?? null),
            'e_border' => $this->str($p[$set[2]] ?? null), 'w_border' => $this->str($p[$set[3]] ?? null),
            'n_dim' => $this->num($p[$set[4]] ?? null), 's_dim' => $this->num($p[$set[5]] ?? null),
            'e_dim' => $this->num($p[$set[6]] ?? null), 'w_dim' => $this->num($p[$set[7]] ?? null),
            'measured_area' => $this->num($p['Survey_Area'] ?? null),
        ]);
        $officeId = $o['legacy'] ? $this->engineeringOfficeId() : $o['office_id'];
        $boundary = DB::table('parcel_boundaries')->where('parcel_id', $parcelId)->first(['id', 'engineering_office_id']);

        if ($boundary === null) {
            // Nothing in the file about this parcel's boundary: none is made.
            if ($values === []) {
                return;
            }
            DB::table('parcel_boundaries')->insert($values + [
                'parcel_id' => $parcelId,
                'engineering_office_id' => $officeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // The office is set on insert only, never over a manual assignment.
            DB::table('parcel_boundaries')->where('id', $boundary->id)->update($values + [
                'engineering_office_id' => $boundary->engineering_office_id ?? $officeId,
                'updated_at' => now(),
            ]);
        }
        $stats['boundaries']++;
    }

    /**
     * The parcel's survey decision, from Qrar (read as the decision number,
     * as the source code, or not at all), Report_No and Folder.
     *
     * @param  array<string, mixed>  $p
     * @param  array<string, mixed>  $o
     * @param  array<string, int>  $stats
     */
    private function importDecision(array $p, int $parcelId, array $o, array &$stats): void
    {
        $qrar = $p['Qrar'] ?? null;
        $values = $this->filled([
            'qrar_no' => $o['qrar'] === 'number' ? $this->str($qrar) : null,
            'qrar_source' => $o['qrar'] === 'source' ? $this->enum('qrar_source', $qrar) : null,
            'report_no' => $this->str($p['Report_No'] ?? null),
            'folder' => $o['folder'] === 'folder' ? $this->str($p['Folder'] ?? null) : null,
        ]);

        if ($values === []) {
            return;
        }

        $decision = DB::table('survey_decisions')->where('parcel_id', $parcelId)->value('id');

        if ($decision === null) {
            DB::table('survey_decisions')->insert($values + [
                'parcel_id' => $parcelId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stats['decisions']++;
        } else {
            DB::table('survey_decisions')->where('id', $decision)->update($values + ['updated_at' => now()]);
        }
    }

    /**
     * The owner's portfolio of that name, made if new, with the parcel in it
     * — moved there if it sat in another of the owner's portfolios.
     *
     * @param  array<string, int>  $stats
     */
    private function placeInPortfolio(int $ownerId, int $parcelId, string $name, array &$stats): void
    {
        $name = mb_substr($name, 0, 100);
        $portfolioId = DB::table('owner_portfolios')->where('owner_id', $ownerId)->where('name', $name)->value('id');

        if ($portfolioId === null) {
            $portfolioId = DB::table('owner_portfolios')->insertGetId([
                'owner_id' => $ownerId, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $stats['portfolios']++;
        }

        $link = DB::table('owner_portfolio_parcels')->where('owner_id', $ownerId)->where('parcel_id', $parcelId)->first(['id', 'owner_portfolio_id']);

        if ($link === null) {
            DB::table('owner_portfolio_parcels')->insert([
                'owner_portfolio_id' => $portfolioId, 'owner_id' => $ownerId, 'parcel_id' => $parcelId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } elseif ((int) $link->owner_portfolio_id !== (int) $portfolioId) {
            DB::table('owner_portfolio_parcels')->where('id', $link->id)->update(['owner_portfolio_id' => $portfolioId, 'updated_at' => now()]);
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

    /**
     * The district a parcel is filed under: the one given to that parcel on
     * the review screen; else, if its District value (or all parcels) is set
     * to go by location, the district its polygon lies in; else — and
     * whenever it lies in none — the district its District value stands for.
     *
     * @param  array<string, mixed>  $o
     * @param  array<string, int>  $stats
     */
    private function resolveDistrict(string $geoId, ?string $name, mixed $geometry, array $o, array &$stats): ?int
    {
        if (isset($o['parcel_districts'][$geoId])) {
            return $o['parcel_districts'][$geoId];
        }

        $method = $o['district_match'];
        foreach ($o['districts'] as $row) {
            if (trim($row['name']) === (string) $name && $row['match'] !== '') {
                $method = $row['match'];
                break;
            }
        }

        if ($method === 'map') {
            $district = Locator::locate($geometry)['district'] ?? null;
            if ($district !== null) {
                $stats['located']++;

                return $district;
            }
            $stats['not_located']++;
        }

        return $this->districtFor($name, $o);
    }

    /**
     * The district a feature's District value stands for: the existing one
     * it was matched to, or one of that name made in the city chosen for it
     * (or the default city). With no city to put it in, none.
     *
     * @param  array<string, mixed>  $o
     */
    private function districtFor(?string $name, array $o): ?int
    {
        // Parcels whose District is empty share one row, keyed by ''.
        $row = null;
        foreach ($o['districts'] as $candidate) {
            if (trim($candidate['name']) === (string) $name) {
                $row = $candidate;
                break;
            }
        }

        if ($row !== null && $row['district_id'] !== null) {
            return $row['district_id'];
        }

        if ($name === null) {
            // No name of its own: made under the name chosen for the row.
            $name = $row['new_name'] ?? '';
            if ($name === '') {
                return null;
            }
        }

        $cityId = $row['city_id'] ?? null;
        $cityId ??= $o['default_city_id'];
        if ($cityId === null && $o['legacy']) {
            $cityId = $this->cityId();
        }

        return $cityId === null ? null : $this->findOrCreate('districts', ['name_ar' => $name, 'city_id' => $cityId]);
    }

    /**
     * The plan a parcel is filed under — and through it, its district: a
     * parcel belongs to a district only by way of its plan.
     *
     * Plan numbers are unique across the system, so a number already on
     * record is that plan: it is given the district if it had none, and kept
     * where it is if it has another (noted for the result). A placeholder
     * number ("بدون") or none at all means no real plan: the parcel goes into
     * the district's own stand-in plan, "بدون — district — city", so it keeps
     * its district — or into no plan at all, as chosen.
     *
     * @param  array<string, mixed>  $o
     */
    private function planFor(?string $planNo, ?int $districtId, array $o): ?int
    {
        if ($planNo === null || in_array($planNo, $o['plan_placeholders'], true)) {
            if ($o['no_plan'] !== 'district_plan' || $districtId === null) {
                return null;
            }

            $district = DB::table('districts as d')->leftJoin('cities as c', 'c.id', '=', 'd.city_id')
                ->where('d.id', $districtId)->first(['d.name_ar', 'c.name_ar as city']);
            $label = $o['plan_placeholders'][0] ?? 'بدون';
            $planNo = mb_substr(implode(' — ', array_filter([$label, $district?->name_ar, $district?->city])), 0, 100);
        }

        $plan = DB::table('plans')->where('plan_no', $planNo)->first(['id', 'district_id']);

        if ($plan === null) {
            return (int) DB::table('plans')->insertGetId([
                'plan_no' => $planNo,
                'district_id' => $districtId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($plan->district_id === null && $districtId !== null) {
            DB::table('plans')->where('id', $plan->id)->update(['district_id' => $districtId, 'updated_at' => now()]);
        } elseif ($districtId !== null && (int) $plan->district_id !== $districtId) {
            $this->planConflicts[$planNo] = ($this->planConflicts[$planNo] ?? 0) + 1;
        }

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

    /**
     * Only the values the file actually gives — an empty one is left out so
     * it never overwrites what is on record.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filled(array $values): array
    {
        return array_filter($values, static fn (mixed $v): bool => $v !== null);
    }

    /**
     * A fixed-choice value from the file: the label itself ("قديم"), or the
     * ArcGIS domain's 1-based numeric code. Anything else is noted for the
     * result's warnings and left out, so it changes nothing.
     */
    private function enum(string $field, mixed $value): ?string
    {
        $value = $this->str($value);
        if ($value === null) {
            return null;
        }

        $resolved = self::resolveEnum($field, $value);
        if ($resolved === null) {
            $this->unknown[$field][$value] = ($this->unknown[$field][$value] ?? 0) + 1;
        }

        return $resolved;
    }

    /**
     * The plans noted by planFor() during this import, cleared for the next.
     *
     * @return array<string, int>
     */
    private function takePlanConflicts(): array
    {
        $conflicts = $this->planConflicts;
        $this->planConflicts = [];

        return $conflicts;
    }

    /**
     * The values noted by enum() during this import, cleared for the next.
     *
     * @return array<string, array<string, int>>
     */
    private function takeUnknown(): array
    {
        $unknown = $this->unknown;
        $this->unknown = [];

        return $unknown;
    }

    /**
     * The label a file value stands for in a fixed-choice column — the
     * label itself, or its ArcGIS domain code — or null if it is neither.
     * Public so the review screen can show which values will be understood.
     */
    public static function resolveEnum(string $field, string $value): ?string
    {
        $value = trim($value);
        $allowed = DatabaseEnum::for($field);

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        if (is_numeric($value)) {
            $code = self::ENUM_VALUES[$field][(int) (float) $value - 1] ?? null;
            if ($code !== null && in_array($code, $allowed, true)) {
                return $code;
            }
        }

        return null;
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
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function num(mixed $value): ?float
    {
        return ($value === null || $value === '' || ! is_numeric($value)) ? null : (float) $value;
    }
}
