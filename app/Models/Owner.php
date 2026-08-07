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
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $whatsapp
 */
class Owner extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'national_id', 'phone', 'email', 'whatsapp'];

    protected static function booted(): void
    {
        // Keep the indexed lookup column in sync with every phone change.
        static::saving(function (Owner $owner): void {
            $owner->phone_normalized = $owner->phone === null
                ? null
                : (self::normalisePhone($owner->phone) ?: null);
        });
    }

    /**
     * Reduces any accepted phone format (05…, +9665…, 009665…) to one
     * comparable national form: digits only, country prefix and leading
     * zeros stripped.
     */
    public static function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        foreach (['00966', '966'] as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                $digits = substr($digits, strlen($prefix));
                break;
            }
        }

        return ltrim($digits, '0');
    }

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
