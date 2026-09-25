<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasBoundary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasBoundary;

    protected $fillable = ['name_ar', 'name_en', 'iso_code'];

    /**
     * The boundary polygon is never serialised: MariaDB returns it as
     * binary WKB, which would break any JSON it landed in.
     */
    protected $hidden = ['geom'];

    /** @return HasMany<Region, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
