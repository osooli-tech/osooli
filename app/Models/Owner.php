<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Land owner. Authenticates only through the mobile API (Sanctum tokens) —
 * dashboard users live in the separate `users` table.
 *
 * @property int $id
 * @property string $name
 * @property string|null $national_id
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $whatsapp
 */
class Owner extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'national_id', 'phone', 'email', 'whatsapp'];

    /** @return BelongsToMany<Deed, $this> */
    public function deeds(): BelongsToMany
    {
        return $this->belongsToMany(Deed::class, 'deed_owners')
            ->withPivot('ownership_share', 'source_gdb_id')
            ->withTimestamps();
    }

    /** @return HasMany<DeedOwner, $this> */
    public function deedOwners(): HasMany
    {
        return $this->hasMany(DeedOwner::class);
    }

    /** @return HasMany<ModificationRequest, $this> */
    public function modificationRequests(): HasMany
    {
        return $this->hasMany(ModificationRequest::class, 'requested_by');
    }

    /**
     * Parcels this owner holds, reached through their deeds.
     *
     * Every API read must start from here so an owner can only ever see
     * their own parcels.
     *
     * @return Builder<Parcel>
     */
    public function parcels(): Builder
    {
        return Parcel::whereHas(
            'deeds.owners',
            fn (Builder $query) => $query->whereKey($this->getKey())
        );
    }
}
