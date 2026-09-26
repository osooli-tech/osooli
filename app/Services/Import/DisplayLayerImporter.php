<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads a geodatabase layer into the projects or buildings table — the map's
 * identification layers: a name, a code, an area, a length and a shape.
 *
 * Their features carry no identifier that survives a re-export (codes such
 * as FARM or Building repeat across dozens of shapes), so there is nothing
 * to match an existing row against. The layer is either added to what is
 * there, or replaces it outright, as chosen on the review screen.
 */
final class DisplayLayerImporter
{
    public const TABLES = ['projects', 'buildings'];

    public const MODES = ['replace', 'append'];

    /** @return int rows written */
    public function import(string $file, string $table, string $mode): int
    {
        if (! in_array($table, self::TABLES, true) || ! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Cannot import into {$table} ({$mode}).");
        }

        $data = json_decode((string) file_get_contents($file), true);
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        return DB::transaction(function () use ($features, $table, $mode): int {
            if ($mode === 'replace') {
                DB::table($table)->delete();
            }

            $written = 0;
            foreach ($features as $feature) {
                $p = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

                $id = DB::table($table)->insertGetId([
                    'name' => $this->text($this->field($p, 'Name')),
                    'code' => $this->text($this->field($p, 'Code')),
                    'area' => $this->number($this->field($p, 'Shape_Area')),
                    'length' => $this->number($this->field($p, 'Shape_Length')),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (is_array($feature['geometry'] ?? null)) {
                    DB::update(
                        "UPDATE {$table} SET geom = ".Spatial::fromGeoJson().' WHERE id = ?',
                        [Spatial::multiPolygonJson($feature['geometry']), $id]
                    );
                }
                $written++;
            }

            return $written;
        });
    }

    /**
     * A property by name, whatever its case — ArcGIS writes SHAPE_Area in
     * one layer and Shape_Area in the next.
     *
     * @param  array<string, mixed>  $properties
     */
    private function field(array $properties, string $name): mixed
    {
        foreach ($properties as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
