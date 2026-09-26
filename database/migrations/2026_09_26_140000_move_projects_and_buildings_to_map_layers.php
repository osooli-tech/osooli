<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects and buildings become custom map layers like any other — named,
 * coloured, shown or hidden, downloaded and deleted from the one screen —
 * instead of two tables of their own with an importer and a map layer each.
 *
 * Their rows move into a layer apiece, under the attribute names the
 * geodatabase gave them (Name, Code, Shape_Area, Shape_Length), so the next
 * import of the same file lines up with them. The colours chosen for them on
 * the map come along; they stay shown when the map opens, as they were.
 */
return new class extends Migration
{
    /** table => [layer name, colour setting, default colour] */
    private const LAYERS = [
        'projects' => ['المشاريع', 'projects_fill', '#c9a84c'],
        'buildings' => ['المباني', 'buildings_fill', '#4a90d9'],
    ];

    private const FIELDS = [
        ['name' => 'Name', 'type' => 'String'],
        ['name' => 'Code', 'type' => 'String'],
        ['name' => 'Shape_Area', 'type' => 'Real'],
        ['name' => 'Shape_Length', 'type' => 'Real'],
    ];

    public function up(): void
    {
        $setting = DB::table('map_appearance_settings')->first();
        $overrides = $setting === null ? [] : (json_decode((string) $setting->overrides, true) ?: []);

        foreach (self::LAYERS as $table => [$name, $colourKey, $colour]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                $layerId = DB::table('map_layers')->where('name', $name)->value('id')
                    ?? DB::table('map_layers')->insertGetId([
                        'name' => $name,
                        'geometry_type' => 'MultiPolygon',
                        'fields' => json_encode(self::FIELDS),
                        'color' => $overrides[$colourKey] ?? $colour,
                        'visible_by_default' => true,
                        'source' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach (DB::table($table)->orderBy('id')->get(['id', 'name', 'code', 'area', 'length']) as $row) {
                    $properties = json_encode([
                        'Name' => $row->name,
                        'Code' => $row->code,
                        'Shape_Area' => $row->area,
                        'Shape_Length' => $row->length,
                    ], JSON_UNESCAPED_UNICODE);

                    DB::insert(
                        "INSERT INTO map_layer_features (map_layer_id, properties, geom, created_at, updated_at)
                         SELECT ?, ?, geom, ?, ? FROM {$table} WHERE id = ?",
                        [$layerId, $properties, now(), now(), $row->id]
                    );
                }

                DB::table('map_layers')->where('id', $layerId)->update([
                    'feature_count' => DB::table('map_layer_features')->where('map_layer_id', $layerId)->count(),
                ]);
            }

            Schema::drop($table);
            unset($overrides[$colourKey]);
        }

        if ($setting !== null) {
            DB::table('map_appearance_settings')->where('id', $setting->id)->update(['overrides' => json_encode($overrides, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * The tables come back empty: the rows stay in their layers, which are
     * not removed — deleting a layer someone may have edited since is not
     * for a rollback to do.
     */
    public function down(): void
    {
        foreach (array_keys(self::LAYERS) as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->id();
                $t->string('name')->nullable();
                $t->string('code')->nullable();
                $t->double('area')->nullable();
                $t->double('length')->nullable();
                $t->timestamps();
            });
            PortableSchema::addGeometryColumn($table);
        }
    }
};
