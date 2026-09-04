<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeedStatus;
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

    /** Every deed this owner has ever been linked to — includes a deed a
     *  parcel has since been re-issued past (e.g. a sale or gift transfer),
     *  so this alone cannot answer "what does this owner hold today". */
    /** @return BelongsToMany<Deed, $this> */
    public function deeds(): BelongsToMany
    {
        return $this->belongsToMany(Deed::class, 'deed_owners')
            ->withPivot('ownership_share', 'source_gdb_id')
            ->withTimestamps();
    }

    /**
     * This owner's deeds, restricted to the ones that are still each
     * parcel's active deed. A parcel can carry more than one deed over time
     * (Deed_No changes on a sale, gift, or re-registration); once a newer
     * deed supersedes it, the old one stays in `deeds()` for history but
     * must drop out here, or a transferred-away parcel would still count as
     * held by its old owner.
     *
     * @return BelongsToMany<Deed, $this>
     */
    public function currentDeeds(): BelongsToMany
    {
        $relation = $this->deeds();

        // Query\Builder, not Eloquent\Builder: "d2" is a bare SQL alias for
        // the subquery, not a model Larastan can check columns against.
        $relation->getQuery()->whereIn('deeds.id', function (\Illuminate\Database\Query\Builder $query): void {
            $query->selectRaw('MAX(d2.id)')
                ->from('deeds as d2')
                ->whereColumn('d2.parcel_id', 'deeds.parcel_id')
                ->where('d2.deed_status', DeedStatus::Updated->value);
        });

        return $relation;
    }

    /** @return HasMany<OwnerPortfolio, $this> */
    public function portfolios(): HasMany
    {
        return $this->hasMany(OwnerPortfolio::class);
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
     * Parcels this owner currently holds, reached through each parcel's
     * active deed (not just any deed they have ever been linked to) — a
     * parcel re-issued to a new owner must stop counting toward the old one.
     *
     * Every API read must start from here so an owner can only ever see
     * their own parcels.
     *
     * @return Builder<Parcel>
     */
    public function parcels(): Builder
    {
        return Parcel::whereHas(
            'currentDeed.owners',
            fn (Builder $query) => $query->whereKey($this->getKey())
        );
    }
}
