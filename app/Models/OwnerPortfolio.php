<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An owner-defined grouping of their own parcels — e.g. splitting holdings
 * across several projects. Nothing here is synced from ArcGIS; the owner (or
 * an admin on their behalf) creates and edits these directly.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $name
 */
class OwnerPortfolio extends Model
{
    protected $fillable = ['owner_id', 'name'];

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** @return BelongsToMany<Parcel, $this> */
    public function parcels(): BelongsToMany
    {
        return $this->belongsToMany(Parcel::class, 'owner_portfolio_parcels')
            ->withTimestamps();
    }
}
