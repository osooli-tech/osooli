<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\MapLayer;
use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads a geodatabase layer into a custom map layer: a new one, or one
 * already on the map — added to, or replaced — as chosen on the review
 * screen. Every attribute is kept as it came.
 */
final class CustomLayerImporter
{
    public const MODES = ['replace', 'append'];

    /** Colours handed to new layers in turn, so two new layers never look alike. */
    private const PALETTE = ['#8e44ad', '#16a085', '#d35400', '#2c3e50', '#c0392b', '#27ae60', '#2980b9', '#f39c12'];

    /**
     * @param  list<array{name: string, type: string}>  $fields
     * @return array{layer_id: int, name: string, written: int}
     */
    public function import(string $file, ?int $layerId, string $newName, string $mode, array $fields, ?string $geometryType, ?string $source, ?int $userId): array
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unknown mode {$mode}.");
        }

        $data = json_decode((string) file_get_contents($file), true);
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        return DB::transaction(function () use ($features, $layerId, $newName, $mode, $fields, $geometryType, $source, $userId): array {
            if ($layerId === null) {
                $layer = MapLayer::create([
                    'name' => $this->freeName($newName),
                    'geometry_type' => $geometryType,
                    'fields' => $fields,
                    'color' => self::PALETTE[MapLayer::count() % count(self::PALETTE)],
                    'source' => $source,
                    'created_by' => $userId,
                ]);
            } else {
                $layer = MapLayer::query()->lockForUpdate()->findOrFail($layerId);
                if ($mode === 'replace') {
                    DB::table('map_layer_features')->where('map_layer_id', $layer->id)->delete();
                }
            }

            $written = 0;
            foreach ($features as $feature) {
                $geometry = is_array($feature['geometry'] ?? null) ? json_encode($feature['geometry']) : null;
                $properties = json_encode(is_array($feature['properties'] ?? null) ? $feature['properties'] : [], JSON_UNESCAPED_UNICODE);

                DB::insert(
                    'INSERT INTO map_layer_features (map_layer_id, properties, geom, created_at, updated_at)
                     VALUES (?, ?, '.($geometry === null ? 'NULL' : Spatial::anyFromGeoJson()).', ?, ?)',
                    array_values(array_filter([$layer->id, $properties, $geometry, now(), now()], static fn (mixed $v): bool => $v !== null))
                );
                $written++;
            }

            $layer->update([
                'feature_count' => DB::table('map_layer_features')->where('map_layer_id', $layer->id)->count(),
                'fields' => $this->mergeFields($layer->fields ?? [], $fields),
                'geometry_type' => $this->mergeType($layer->geometry_type, $geometryType, $mode, $layerId === null),
                'source' => $source ?? $layer->source,
            ]);

            return ['layer_id' => $layer->id, 'name' => $layer->name, 'written' => $written];
        });
    }

    /** The name, or "name (2)", "name (3)" … if a layer of that name exists. */
    private function freeName(string $name): string
    {
        $name = mb_substr(trim($name), 0, 140) ?: 'layer';
        $candidate = $name;
        for ($n = 2; MapLayer::where('name', $candidate)->exists(); $n++) {
            $candidate = "{$name} ({$n})";
        }

        return $candidate;
    }

    /**
     * @param  list<array{name: string, type: string}>  $existing
     * @param  list<array{name: string, type: string}>  $incoming
     * @return list<array{name: string, type: string}>
     */
    private function mergeFields(array $existing, array $incoming): array
    {
        $byName = [];
        foreach ([...$existing, ...$incoming] as $field) {
            $byName[$field['name']] ??= $field;
        }

        return array_values($byName);
    }

    private function mergeType(?string $current, ?string $incoming, string $mode, bool $new): ?string
    {
        if ($new || $mode === 'replace' || $current === null) {
            return $incoming;
        }

        return $incoming === null || $incoming === $current ? $current : 'Mixed';
    }
}
