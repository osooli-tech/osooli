<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * An owner's offer to sell one of their parcels. Other owners only ever see
 * an approved listing whose seller still holds the parcel, and never one a
 * member of staff hid from them.
 *
 * @property int $id
 * @property int $parcel_id
 * @property int $owner_id
 * @property string $contact_phone
 * @property ListingStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 */
class ParcelListing extends Model
{
    protected $fillable = [
        'parcel_id',
        'owner_id',
        'contact_phone',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'reviewed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /** @return BelongsTo<Owner, $this> the owner who asked for the listing */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsToMany<Owner, $this> owners staff hid this listing from */
    public function blockedOwners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'parcel_listing_blocks')->withPivot('created_by')->withTimestamps();
    }

    /**
     * Listings one owner may see: approved, someone else's, not hidden from
     * them, on a parcel the seller still holds — a parcel sold since drops out
     * on its own — and not on a parcel the viewer holds too.
     *
     * @param  Builder<ParcelListing>  $query
     * @return Builder<ParcelListing>
     */
    public function scopeVisibleTo(Builder $query, Owner $viewer): Builder
    {
        return $query
            ->where('parcel_listings.status', ListingStatus::Approved->value)
            ->where('parcel_listings.owner_id', '!=', $viewer->getKey())
            ->whereDoesntHave('blockedOwners', fn (Builder $q) => $q->whereKey($viewer->getKey()))
            ->whereHas('parcel.heldDeed.owners', fn (Builder $q) => $q->whereColumn('owners.id', 'parcel_listings.owner_id'))
            ->whereDoesntHave('parcel.heldDeed.owners', fn (Builder $q) => $q->whereKey($viewer->getKey()));
    }
}
