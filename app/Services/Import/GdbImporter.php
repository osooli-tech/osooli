<?php

declare(strict_types=1);

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * An uploaded geodatabase: every layer looked at, each put where the review
 * screen said — parcels (with their deeds, owners, boundaries and survey
 * decisions), projects, buildings, or nowhere.
 *
 * analyze() reports what is in the file and suggests a decision for every
 * choice there is; commit() applies the choices that were confirmed. A plain
 * GeoJSON upload — the escape hatch for a host without GDAL — goes straight
 * to the parcel importer, as before.
 */
final class GdbImporter implements Importer
{
    /** The file's fixed-choice fields, by the column each fills. */
    private const ENUM_FIELDS = [
        'Owner_Type' => 'asset_type',
        'Land_Trasaction' => 'land_transaction',
        'Allocation_Method' => 'allocation_method',
        'Fall_In' => 'fall_in',
        'Deed_Status' => 'deed_status',
        'Deed_Class' => 'deed_class',
    ];

    /** Plan numbers that mean "no plan", when the file has them. */
    private const PLAN_PLACEHOLDERS = ['بدون', 'لا يوجد', 'لايوجد', '-', '0'];

    public const ROLES = ['parcels', 'projects', 'buildings', 'ignore'];

    public function __construct(
        private readonly GdbInspector $inspector,
        private readonly ParcelGeoJsonImporter $parcels,
        private readonly DisplayLayerImporter $display,
    ) {}

    public function analyze(string $sourcePath): ImportPreview
    {
        if ($this->isGeoJson($sourcePath)) {
            return $this->parcels->analyze($sourcePath);
        }

        $inventory = $this->inspector->inspect($sourcePath, $this->workDir($sourcePath));
        $parcelLayer = $this->layerWithRole($inventory, 'parcels');
        $features = $parcelLayer === null ? [] : $this->features((string) $parcelLayer['file']);
        $preview = $this->parcels->previewFeatures($features);

        return new ImportPreview(
            totalItems: $preview->totalItems,
            willCreate: $preview->willCreate,
            willUpdate: $preview->willUpdate,
            unmatched: $preview->unmatched,
            details: $preview->details + ['gdb' => [
                'layers' => array_map(static function (array $layer): array {
                    unset($layer['file']);

                    return $layer;
                }, $inventory['layers']),
                'attachments' => $inventory['attachments'],
                'relationships' => $inventory['relationships'],
                'system_tables' => $inventory['system_tables'],
                'analysis' => $this->analysis($features),
                'existing' => [
                    'projects' => DB::table('projects')->count(),
                    'buildings' => DB::table('buildings')->count(),
                ],
                'suggested' => $this->suggest($inventory, $features),
            ]],
            warnings: $preview->warnings,
        );
    }

    /** @param  array<string, mixed>  $options  as suggested by analyze(), then edited */
    public function commit(string $sourcePath, array $options = []): ImportResult
    {
        if ($this->isGeoJson($sourcePath)) {
            return $this->parcels->commit($sourcePath, $options);
        }

        $inventory = $this->inspector->inspect($sourcePath, $this->workDir($sourcePath));
        $roles = [];
        foreach (is_array($options['layers'] ?? null) ? $options['layers'] : [] as $choice) {
            if (is_array($choice) && is_string($choice['name'] ?? null) && in_array($choice['role'] ?? null, self::ROLES, true)) {
                $roles[$choice['name']] = $choice['role'];
            }
        }
        $modes = is_array($options['modes'] ?? null) ? $options['modes'] : [];

        $result = null;
        $written = [];
        foreach ($inventory['layers'] as $layer) {
            $role = $roles[$layer['name']] ?? $layer['role'];

            if ($role === 'parcels' && $result === null) {
                $result = $this->parcels->importFeatures($this->features((string) $layer['file']), $options);
            } elseif (in_array($role, DisplayLayerImporter::TABLES, true)) {
                $mode = $modes[$role] ?? 'replace';
                if (in_array($mode, DisplayLayerImporter::MODES, true)) {
                    // A second layer chosen for the same table adds to the first.
                    $mode = isset($written[$role]) ? 'append' : $mode;
                    $written[$role] = ($written[$role] ?? 0) + $this->display->import((string) $layer['file'], $role, $mode);
                }
            }
        }

        $result ??= new ImportResult(created: 0, updated: 0, skipped: 0, errors: 0);

        return new ImportResult(
            created: $result->created,
            updated: $result->updated,
            skipped: $result->skipped,
            errors: $result->errors,
            details: $result->details + ['projects' => $written['projects'] ?? 0, 'buildings' => $written['buildings'] ?? 0],
            warnings: $result->warnings,
        );
    }

    /**
     * What the parcels layer holds, in the terms the choices are made in.
     *
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function analysis(array $features): array
    {
        $count = static function (array $features, string $field): array {
            $counts = [];
            foreach ($features as $feature) {
                $value = trim((string) ($feature['properties'][$field] ?? ''));
                if ($value !== '') {
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
            }
            arsort($counts);

            return $counts;
        };

        $enums = [];
        foreach (self::ENUM_FIELDS as $field => $column) {
            foreach ($count($features, $field) as $value => $n) {
                $enums[$field][] = [
                    'value' => (string) $value,
                    'count' => $n,
                    'label' => ParcelGeoJsonImporter::resolveEnum($column, (string) $value),
                ];
            }
        }

        $owners = $count($features, 'Woner_ID');
        $oddIds = array_values(array_filter(array_map('strval', array_keys($owners)), static fn (string $id): bool => preg_match('/^\d{10}$/', $id) !== 1));

        $borders = static function (array $features, array $fields): int {
            return count(array_filter($features, static function (array $f) use ($fields): bool {
                foreach ($fields as $field) {
                    if (trim((string) ($f['properties'][$field] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            }));
        };

        return [
            'enums' => $enums,
            'plans' => array_slice($count($features, 'Plan_No'), 0, 30, true),
            'plans_empty' => count($features) - array_sum($count($features, 'Plan_No')),
            'deeds_with_number' => array_sum($count($features, 'Deed_No')),
            'deeds_without_number' => count($features) - array_sum($count($features, 'Deed_No')),
            'owners' => count($owners),
            'owner_ids_odd' => array_slice($oddIds, 0, 20),
            'borders_first' => $borders($features, ['N_Border', 'S_Border', 'E_Border', 'W_Border', 'N_Dim', 'S_DIM', 'E_Dim', 'W_Dim']),
            'borders_second' => $borders($features, ['N_Border_2', 'S_Border_2', 'E_Border_2', 'W_Border_2', 'N_Dim_2', 'S_Dim_2', 'E_Dim_2', 'W_Dim_2']),
            'qrar' => array_slice($count($features, 'Qrar'), 0, 10, true),
            'folder' => array_slice($count($features, 'Folder'), 0, 10, true),
            'portfolios' => $count($features, 'Real_Estate_portfolio'),
        ];
    }

    /**
     * A decision for every choice, taken from the file itself: the layers by
     * their names and fields, each district matched to one of the same name
     * where there is exactly one, and so on. Everything here can be changed
     * on the review screen before anything is written.
     *
     * @param  array<string, mixed>  $inventory
     * @param  list<array<string, mixed>>  $features
     * @return array<string, mixed>
     */
    private function suggest(array $inventory, array $features): array
    {
        $districtCounts = [];
        foreach ($features as $feature) {
            $name = trim((string) ($feature['properties']['District'] ?? ''));
            if ($name !== '') {
                $districtCounts[$name] = ($districtCounts[$name] ?? 0) + 1;
            }
        }
        arsort($districtCounts);

        $districts = [];
        $cities = [];
        foreach ($districtCounts as $name => $n) {
            $matches = DB::table('districts as d')
                ->join('cities as c', 'c.id', '=', 'd.city_id')
                ->where('d.name_ar', (string) $name)
                ->limit(10)
                ->get(['d.id', 'd.city_id', 'c.name_ar as city']);

            $districts[] = [
                'name' => (string) $name,
                'count' => $n,
                'district_id' => $matches->count() === 1 ? (int) $matches[0]->id : null,
                'city_id' => null,
                'candidates' => $matches->map(static fn (object $m): array => ['id' => (int) $m->id, 'city' => (string) $m->city])->all(),
            ];
            if ($matches->count() === 1) {
                $cities[(int) $matches[0]->city_id] = ($cities[(int) $matches[0]->city_id] ?? 0) + $n;
            }
        }
        arsort($cities);

        $plans = [];
        foreach ($features as $feature) {
            $plan = trim((string) ($feature['properties']['Plan_No'] ?? ''));
            if (in_array($plan, self::PLAN_PLACEHOLDERS, true)) {
                $plans[$plan] = true;
            }
        }

        $qrar = array_filter(array_map(static fn (array $f): string => trim((string) ($f['properties']['Qrar'] ?? '')), $features));
        $folder = array_filter(array_map(static fn (array $f): string => trim((string) ($f['properties']['Folder'] ?? '')), $features));
        $portfolios = array_filter(array_map(static fn (array $f): string => trim((string) ($f['properties']['Real_Estate_portfolio'] ?? '')), $features));

        // A list, not name => role: layer names become Livewire property
        // paths on the review screen, and a path cannot hold any name.
        $layers = array_map(static fn (array $layer): array => ['name' => $layer['name'], 'role' => $layer['role']], $inventory['layers']);

        return [
            'layers' => $layers,
            // Replace by default: the file is the source of these layers, and
            // adding would duplicate every shape each time a file is re-run.
            'modes' => ['projects' => 'replace', 'buildings' => 'replace'],
            'districts' => $districts,
            'default_city_id' => array_key_first($cities),
            'plan_placeholders' => implode('، ', array_keys($plans) ?: ['بدون']),
            'borders' => 'first',
            // Codes of the source domain run 1-3; anything past that is a
            // decision number.
            'qrar' => $qrar === [] ? 'ignore'
                : (array_filter($qrar, static fn (string $v): bool => ! is_numeric($v) || (int) $v > 3) !== [] ? 'number' : 'source'),
            // A folder is a short reference; sentences are notes.
            'folder' => $folder === [] ? 'ignore'
                : (array_filter($folder, static fn (string $v): bool => mb_strlen($v) > 20 || str_contains($v, ' ')) !== [] ? 'ignore' : 'folder'),
            'portfolios' => $portfolios !== [],
            'deedless' => 'placeholder',
            'office_id' => DB::table('engineering_offices')->where('name', 'مكتب الإسناد العالمي للاستشارات الهندسية')->value('id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $inventory
     * @return array<string, mixed>|null
     */
    private function layerWithRole(array $inventory, string $role): ?array
    {
        foreach ($inventory['layers'] as $layer) {
            if ($layer['role'] === $role) {
                return $layer;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function features(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data['features'] ?? null) ? array_values($data['features']) : [];
    }

    private function isGeoJson(string $path): bool
    {
        $path = strtolower($path);

        return str_ends_with($path, '.geojson') || str_ends_with($path, '.json');
    }

    private function workDir(string $sourcePath): string
    {
        return dirname($sourcePath).'/work';
    }
}
