<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Database\Spatial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the boundaries in database/data/boundaries (see build.py there) onto
 * the country, regions, cities and districts NationalAddressSeeder created.
 *
 * Rows are found by their National Address id, so this runs after that
 * seeder. A boundary drawn by hand on the dashboard (boundary_source
 * 'manual') is never replaced; anything else is refreshed, so the seeder
 * can be run again when the data files are rebuilt.
 */
class NationalAddressBoundariesSeeder extends Seeder
{
    private const DATA = 'data/boundaries';

    /** File => [table, property holding the key, column it matches]. */
    private const LEVELS = [
        'country' => ['countries', 'iso_code', 'iso_code'],
        'regions' => ['regions', 'region_id', 'national_address_id'],
        'cities' => ['cities', 'city_id', 'national_address_id'],
        'districts' => ['districts', 'district_id', 'national_address_id'],
    ];

    public function run(): void
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        foreach (self::LEVELS as $file => [$table, $property, $column]) {
            $features = $this->load($file);
            $stats = ['loaded' => 0, 'manual_kept' => 0, 'no_row' => 0];

            DB::transaction(function () use ($features, $table, $property, $column, &$stats): void {
                foreach ($features as $feature) {
                    $key = $feature['properties'][$property] ?? null;
                    $row = DB::table($table)->where($column, $key)->first(['id', 'boundary_source']);

                    if ($row === null) {
                        $stats['no_row']++;

                        continue;
                    }

                    if ($row->boundary_source === 'manual') {
                        $stats['manual_kept']++;

                        continue;
                    }

                    DB::update(
                        "UPDATE {$table} SET geom = ".Spatial::fromGeoJson().', boundary_source = ?, updated_at = ? WHERE id = ?',
                        [Spatial::multiPolygonJson($feature['geometry']), $feature['properties']['source'], now(), $row->id]
                    );
                    $stats['loaded']++;
                }
            });

            $this->command?->info(sprintf(
                '%s: %d boundaries loaded, %d hand-drawn kept, %d without a matching row',
                $table, $stats['loaded'], $stats['manual_kept'], $stats['no_row']
            ));
        }
    }

    /** @return list<array<string, mixed>> */
    private function load(string $name): array
    {
        $path = database_path(self::DATA."/{$name}.geojson.gz");
        $json = @gzdecode((string) @file_get_contents($path));
        $data = $json === false ? null : json_decode($json, true);

        if (! is_array($data) || ! is_array($data['features'] ?? null)) {
            throw new RuntimeException("Missing or unreadable boundary file: {$path}");
        }

        return $data['features'];
    }
}
