<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name_ar
 * @property string $name_en
 */
class District extends Model
{
    protected $fillable = ['city_id', 'name_ar', 'name_en'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }
}
