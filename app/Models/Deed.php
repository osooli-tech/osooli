<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $deed_no
 * @property string|null $deed_date_hijri
 * @property string|null $deed_status
 * @property string|null $deed_class
 * @property string|null $deed_area
 */
class Deed extends Model
{
    protected $fillable = [
        'parcel_id',
        'deed_no',
        'deed_date_hijri',
        'deed_status',
        'deed_class',
        'deed_area',
    ];

    protected function casts(): array
    {
        return [
            'deed_area' => 'decimal:2',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'deed_owners')
            ->withPivot('ownership_share', 'source_gdb_id')
            ->withTimestamps();
    }

    public function deedOwners(): HasMany
    {
        return $this->hasMany(DeedOwner::class);
    }
}
