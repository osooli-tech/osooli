<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A building footprint on the map — an identification/display layer only,
 * per the client's own description. Deliberately not related to Parcel: a
 * building routinely overlaps more than one parcel, so no single parcel_id
 * could represent it correctly.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $code
 * @property float|null $area
 * @property float|null $length
 */
class Building extends Model
{
    protected $fillable = [
        'name',
        'code',
        'area',
        'length',
    ];
}
