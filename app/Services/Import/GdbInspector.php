<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Geo\LayerNames;
use Symfony\Component\Process\Process;

/**
 * Everything an uploaded geodatabase holds, laid out for the review screen:
 * each data layer with its geometry, feature count, coordinate system and a
 * profile of every field (how full, how many distinct values, examples);
 * the attachment tables and relationship classes, if any; and the ArcGIS
 * system tables, named so it is plain they were seen and set aside.
 *
 * Every data layer is converted to GeoJSON (EPSG:4326) under the batch's
 * work directory while it is being looked at, so the commit reads the very
 * files the review described. The inventory is cached there too: analysing
 * and committing one batch inspects the geodatabase once.
 */
final class GdbInspector
{
    /** Distinct values kept per field as examples. */
    private const SAMPLES = 5;

    public function __construct(private readonly GdbConverter $converter) {}

    /**
     * @return array{
     *     layers: list<array{name: string, file: string, geometry: string|null, count: int, crs: string|null,
     *         role: string, fields: list<array{name: string, type: string, filled: int, distinct: int, samples: list<string>}>}>,
     *     attachments: list<array{table: string, layer: string, count: int}>,
     *     relationships: list<string>,
     *     system_tables: list<string>
     * }
     *
     * @throws ArchiveException
     */
    public function inspect(string $sourcePath, string $workDir): array
    {
        $cache = $workDir.'/inventory.json';

        if (is_file($cache)) {
            $inventory = json_decode((string) file_get_contents($cache), true);
            if (is_array($inventory) && $this->filesPresent($inventory)) {
                return $inventory;
            }
        }

        $gdb = $this->converter->extractGeodatabase($sourcePath, $workDir);
        $info = $this->ogrinfo($gdb);

        $inventory = ['layers' => [], 'attachments' => [], 'relationships' => [], 'system_tables' => []];
        @mkdir($workDir.'/layers', 0775, true);

        foreach ($info['layers'] ?? [] as $i => $layer) {
            $name = (string) ($layer['name'] ?? '');
            $count = (int) ($layer['featureCount'] ?? 0);

            if ($name === '') {
                continue;
            }

            if (str_starts_with($name, 'GDB_')) {
                $inventory['system_tables'][] = $name;

                continue;
            }

            if (str_ends_with(strtoupper($name), '__ATTACH')) {
                $inventory['attachments'][] = ['table' => $name, 'layer' => substr($name, 0, -8), 'count' => $count];

                continue;
            }

            $file = $workDir.'/layers/'.$i.'.geojson';
            $this->converter->convertLayer($gdb, $name, $file);

            $geometry = $layer['geometryFields'][0] ?? null;
            $fields = array_map(static fn (array $f): string => (string) ($f['name'] ?? ''), $layer['fields'] ?? []);
            $types = array_column($layer['fields'] ?? [], 'type', 'name');

            $inventory['layers'][] = [
                'name' => $name,
                'file' => $file,
                'geometry' => is_array($geometry) ? (string) ($geometry['type'] ?? '') : null,
                'count' => $count,
                'crs' => is_array($geometry) ? $this->crs($geometry['coordinateSystem'] ?? null) : null,
                'role' => $this->role($name, $fields, is_array($geometry)),
                'fields' => $this->profile($file, $fields, $types),
            ];
        }

        $inventory['relationships'] = array_map('strval', array_keys(is_array($info['relationships'] ?? null) ? $info['relationships'] : []));

        // Every layer that matters is GeoJSON now; the unpacked geodatabase
        // can be several times the size of the archive and is not kept.
        $this->converter->discardExtracted($workDir);
        file_put_contents($cache, (string) json_encode($inventory, JSON_UNESCAPED_UNICODE));

        return $inventory;
    }

    /** @return array<string, mixed> */
    private function ogrinfo(string $gdb): array
    {
        $process = new Process([
            (string) config('imports.ogrinfo_path', 'ogrinfo'),
            '-ro', '-so', '-json', '-oo', 'LIST_ALL_TABLES=YES', $gdb,
        ]);
        $process->setTimeout(300);
        $process->run();

        $info = $process->isSuccessful() ? json_decode($process->getOutput(), true) : null;

        if (! is_array($info) || ! is_array($info['layers'] ?? null)) {
            // -json needs GDAL 3.7 or later.
            throw new ArchiveException(
                'ogrinfo could not describe the geodatabase (GDAL 3.7 or later is needed): '
                .trim($process->getErrorOutput())
            );
        }

        return $info;
    }

    /**
     * What a layer most likely is, from its name and fields: the parcels
     * layer carries Geo_ID; buildings and projects are named so; any other
     * layer with a shape becomes a custom map layer. Only a suggestion — the
     * review screen lets it be changed.
     *
     * @param  list<string>  $fields
     */
    private function role(string $name, array $fields, bool $spatial): string
    {
        $builtIn = LayerNames::builtInRole($name);

        return match (true) {
            ! $spatial => 'ignore',
            in_array('Geo_ID', $fields, true) => 'parcels',
            in_array($builtIn, ['projects', 'buildings'], true) => (string) $builtIn,
            default => 'custom',
        };
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, string>  $types
     * @return list<array{name: string, type: string, filled: int, distinct: int, samples: list<string>}>
     */
    private function profile(string $file, array $fields, array $types): array
    {
        $data = json_decode((string) file_get_contents($file), true);
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        $filled = array_fill_keys($fields, 0);
        $values = array_fill_keys($fields, []);

        foreach ($features as $feature) {
            foreach ($fields as $field) {
                $value = $feature['properties'][$field] ?? null;
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    continue;
                }
                $filled[$field]++;
                $key = is_scalar($value) ? trim((string) $value) : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
                $values[$field][$key] = ($values[$field][$key] ?? 0) + 1;
            }
        }

        return array_map(function (string $field) use ($filled, $values, $types): array {
            arsort($values[$field]);

            return [
                'name' => $field,
                'type' => (string) ($types[$field] ?? ''),
                'filled' => $filled[$field],
                'distinct' => count($values[$field]),
                'samples' => array_map(
                    static fn (string $v): string => mb_strlen($v) > 60 ? mb_substr($v, 0, 60).'…' : $v,
                    array_slice(array_map('strval', array_keys($values[$field])), 0, self::SAMPLES)
                ),
            ];
        }, $fields);
    }

    /** "EPSG:32638 — WGS 84 / UTM zone 38N", or whatever part of that is known. */
    private function crs(mixed $system): ?string
    {
        $projjson = is_array($system) ? ($system['projjson'] ?? null) : null;
        if (! is_array($projjson)) {
            return null;
        }

        $code = isset($projjson['id']['authority'], $projjson['id']['code'])
            ? $projjson['id']['authority'].':'.$projjson['id']['code']
            : null;
        $name = isset($projjson['name']) ? (string) $projjson['name'] : null;

        return trim(implode(' — ', array_filter([$code, $name]))) ?: null;
    }

    /** @param  array<string, mixed>  $inventory */
    private function filesPresent(array $inventory): bool
    {
        foreach ($inventory['layers'] ?? [] as $layer) {
            if (! is_file((string) ($layer['file'] ?? ''))) {
                return false;
            }
        }

        return true;
    }
}
