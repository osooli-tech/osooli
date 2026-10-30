<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer asking Sukooki to put them in touch with a listing's seller.
 *
 * @property int $id
 * @property int $parcel_listing_id
 * @property int $owner_id
 * @property string|null $note
 * @property InquiryStatus $status
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 */
class ListingInquiry extends Model
{
    protected $fillable = [
        'parcel_listing_id',
        'owner_id',
        'note',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ParcelListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ParcelListing::class, 'parcel_listing_id');
    }

    /** @return BelongsTo<Owner, $this> the owner who wants to buy */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
