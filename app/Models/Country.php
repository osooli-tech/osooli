<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'iso_code'];

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
