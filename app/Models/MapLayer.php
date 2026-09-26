<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A custom map layer, imported from a geodatabase with every attribute its
 * features had. The features themselves (map_layer_features) are read and
 * written in SQL of their own, geometry included; there is no model for them.
 *
 * @property int $id
 * @property string $name
 * @property string|null $geometry_type
 * @property list<array{name: string, type: string}>|null $fields
 * @property string $color
 * @property bool $visible_by_default
 * @property int $feature_count
 * @property string|null $source
 */
class MapLayer extends Model
{
    protected $fillable = ['name', 'geometry_type', 'fields', 'color', 'visible_by_default', 'feature_count', 'source', 'created_by'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'visible_by_default' => 'boolean',
            'feature_count' => 'integer',
        ];
    }
}
