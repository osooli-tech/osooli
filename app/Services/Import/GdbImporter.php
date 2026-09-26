<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\MapLayer;
use App\Support\Geo\LayerNames;
use App\Support\Geo\Locator;
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

    public const ROLES = ['parcels', 'projects', 'buildings', 'custom', 'ignore'];

    public function __construct(
        private readonly GdbInspector $inspector,
        private readonly ParcelGeoJsonImporter $parcels,
        private readonly DisplayLayerImporter $display,
        private readonly CustomLayerImporter $custom,
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
                'similar' => $this->similarNames($inventory),
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
        $choices = [];
        foreach (is_array($options['layers'] ?? null) ? $options['layers'] : [] as $choice) {
            if (is_array($choice) && is_string($choice['name'] ?? null) && in_array($choice['role'] ?? null, self::ROLES, true)) {
                $choices[$choice['name']] = $choice;
            }
        }
        $modes = is_array($options['modes'] ?? null) ? $options['modes'] : [];

        $result = null;
        $written = [];
        $customLayers = [];
        foreach ($inventory['layers'] as $layer) {
            $choice = $choices[$layer['name']] ?? ['role' => $layer['role']];
            $role = $choice['role'];

            if ($role === 'custom') {
                $done = $this->custom->import(
                    file: (string) $layer['file'],
                    layerId: is_numeric($choice['target'] ?? null) ? (int) $choice['target'] : null,
                    newName: trim((string) ($choice['new_name'] ?? '')) ?: $layer['name'],
                    mode: in_array($choice['mode'] ?? null, CustomLayerImporter::MODES, true) ? (string) $choice['mode'] : 'replace',
                    fields: array_map(static fn (array $f): array => ['name' => $f['name'], 'type' => $f['type']], $layer['fields']),
                    geometryType: $layer['geometry'],
                    source: is_string($options['source_name'] ?? null) ? $options['source_name'] : null,
                    userId: is_numeric($options['user_id'] ?? null) ? (int) $options['user_id'] : null,
                );
                $customLayers[$done['name']] = $done['written'];

                continue;
            }

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
            details: $result->details + [
                'projects' => $written['projects'] ?? 0,
                'buildings' => $written['buildings'] ?? 0,
                'custom' => array_sum($customLayers),
                'custom_layers' => $customLayers,
            ],
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
            // For giving one parcel a district of its own.
            'parcel_list' => array_slice(array_values(array_filter(array_map(static fn (array $f): array => [
                'geo_id' => trim((string) ($f['properties']['Geo_ID'] ?? '')),
                'parcel_no' => trim((string) ($f['properties']['Parcel'] ?? '')),
                'district' => trim((string) ($f['properties']['District'] ?? '')),
            ], $features), static fn (array $p): bool => $p['geo_id'] !== '')), 0, 5000),
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
        // Each District value of the file, with where its parcels actually
        // lie by the district and city boundaries on record.
        $byName = [];
        foreach ($features as $feature) {
            $name = trim((string) ($feature['properties']['District'] ?? ''));
            if ($name !== '') {
                $byName[$name][] = $feature;
            }
        }
        uasort($byName, static fn (array $a, array $b): int => count($b) <=> count($a));

        $districts = [];
        $cities = [];
        foreach ($byName as $name => $group) {
            $row = $this->matchDistrict((string) $name, $group);
            foreach ($row['map_cities'] as $cityId => $hits) {
                $cities[$cityId] = ($cities[$cityId] ?? 0) + $hits;
            }
            unset($row['map_cities']);
            $districts[] = $row;
        }
        // No city boundaries to go by: the cities of the districts matched.
        if ($cities === []) {
            foreach ($districts as $row) {
                if ($row['district_id'] !== null) {
                    $cityId = (int) DB::table('districts')->where('id', $row['district_id'])->value('city_id');
                    $cities[$cityId] = ($cities[$cityId] ?? 0) + $row['count'];
                }
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
        // A custom layer whose name is the name of one on the map already
        // (bar spelling) is suggested as that layer, replaced; one merely
        // similar is suggested as new, with a warning beside it.
        $existing = MapLayer::query()->get(['id', 'name']);
        $layers = array_map(static function (array $layer) use ($existing): array {
            $same = $layer['role'] === 'custom'
                ? $existing->first(fn (MapLayer $m): bool => LayerNames::similarity($m->name, $layer['name']) === 1.0)
                : null;

            return [
                'name' => $layer['name'],
                'role' => $layer['role'],
                'target' => $same?->id,
                'new_name' => $layer['name'],
                'mode' => 'replace',
            ];
        }, $inventory['layers']);

        return [
            'layers' => $layers,
            // Replace by default: the file is the source of these layers, and
            // adding would duplicate every shape each time a file is re-run.
            'modes' => ['projects' => 'replace', 'buildings' => 'replace'],
            'districts' => $districts,
            // By name (the table's matches) for all parcels unless changed;
            // each row, and each parcel, can be set otherwise.
            'district_match' => 'name',
            'parcel_districts' => [],
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
            'no_plan' => 'district_plan',
            'office_id' => DB::table('engineering_offices')->where('name', 'مكتب الإسناد العالمي للاستشارات الهندسية')->value('id'),
        ];
    }

    /** Parcels per District value located on the map; enough to tell. */
    private const LOCATE_SAMPLE = 60;

    /** Share of located parcels a district must hold to be suggested by the map. */
    private const MAP_SHARE = 0.6;

    /**
     * Which district on record a District value of the file stands for —
     * by its name, and by where its parcels lie within the district
     * boundaries on record — and how sure that is.
     *
     * @param  list<array<string, mixed>>  $group  the features carrying that name
     * @return array<string, mixed>
     */
    private function matchDistrict(string $name, array $group): array
    {
        $byName = DB::table('districts as d')
            ->join('cities as c', 'c.id', '=', 'd.city_id')
            ->where('d.name_ar', $name)
            ->limit(10)
            ->get(['d.id', 'd.city_id', 'c.name_ar as city']);

        // Where the parcels lie: a sample located against the boundaries.
        $mapDistricts = [];
        $mapCities = [];
        $sampled = 0;
        foreach (array_slice($group, 0, self::LOCATE_SAMPLE) as $feature) {
            $where = Locator::locate($feature['geometry'] ?? null);
            if ($where === null) {
                continue;
            }
            $sampled++;
            if ($where['district'] !== null) {
                $mapDistricts[$where['district']] = ($mapDistricts[$where['district']] ?? 0) + 1;
            }
            if ($where['city'] !== null) {
                $mapCities[$where['city']] = ($mapCities[$where['city']] ?? 0) + 1;
            }
        }
        arsort($mapDistricts);
        arsort($mapCities);

        $mapTop = array_key_first($mapDistricts);
        $share = $mapTop === null || $sampled === 0 ? 0.0 : $mapDistricts[$mapTop] / $sampled;
        $nameIds = $byName->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $nameOne = count($nameIds) === 1 ? $nameIds[0] : null;

        [$districtId, $method, $conflict] = match (true) {
            // Name and map agree — or the map picks one of several of that name.
            $mapTop !== null && in_array($mapTop, $nameIds, true) => [$mapTop, 'name_map', null],
            // The map is clear, the name is spelled otherwise (or clashes).
            $mapTop !== null && $share >= self::MAP_SHARE => [$mapTop, 'map', $nameOne],
            $nameOne !== null => [$nameOne, 'name', $mapTop],
            default => [null, 'none', null],
        };

        return [
            'name' => $name,
            'count' => count($group),
            'district_id' => $districtId,
            // For a district that is not matched: made in the city its parcels lie in.
            'city_id' => $districtId === null ? array_key_first($mapCities) : null,
            'candidates' => $byName->map(static fn (object $m): array => ['id' => (int) $m->id, 'city' => (string) $m->city])->all(),
            'method' => $method,
            'map_share' => (int) round($share * 100),
            'map_sampled' => $sampled,
            'conflict' => $conflict === null || $conflict === $districtId ? null : $this->districtLabel($conflict),
            'map_cities' => $mapCities,
            'match' => '',
        ];
    }

    private function districtLabel(int $id): string
    {
        $row = DB::table('districts as d')->join('cities as c', 'c.id', '=', 'd.city_id')->where('d.id', $id)->first(['d.name_ar', 'c.name_ar as city']);

        return $row === null ? '' : $row->name_ar.' — '.$row->city;
    }

    /**
     * For each layer of the file, by position: the layers on record whose
     * names are close to its own — custom layers, and the built-in parcels,
     * projects and buildings — so the screen can ask whether it is one of
     * them before a near-duplicate is made.
     *
     * @param  array<string, mixed>  $inventory
     * @return array<int, list<array{kind: string, id: int|null, role: string|null, name: string, score: float}>>
     */
    private function similarNames(array $inventory): array
    {
        $candidates = MapLayer::query()->get(['id', 'name'])
            ->map(static fn (MapLayer $m): array => ['kind' => 'custom', 'id' => $m->id, 'role' => null, 'name' => $m->name])
            ->all();
        foreach (LayerNames::BUILT_IN as $role => $names) {
            foreach ($names as $name) {
                $candidates[] = ['kind' => 'built_in', 'id' => null, 'role' => $role, 'name' => $name];
            }
        }

        $similar = [];
        foreach ($inventory['layers'] as $i => $layer) {
            $found = [];
            foreach (LayerNames::similarTo($layer['name'], array_values($candidates)) as $match) {
                // A built-in role counts once, under its closest name, and not
                // at all for the layer already set to it.
                $key = $match['kind'] === 'custom' ? 'custom:'.$match['id'] : 'role:'.$match['role'];
                if (isset($found[$key]) || ($match['kind'] === 'built_in' && $match['role'] === $layer['role'])) {
                    continue;
                }
                $found[$key] = $match;
            }
            if ($found !== []) {
                $similar[$i] = array_values($found);
            }
        }

        return $similar;
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
