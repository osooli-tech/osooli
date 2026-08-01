<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EngineeringOffice extends Model
{
    protected $fillable = ['name', 'license_no', 'phone', 'email'];

    /** @return HasMany<ParcelBoundary, $this> */
    public function parcelBoundaries(): HasMany
    {
        return $this->hasMany(ParcelBoundary::class);
    }
}
