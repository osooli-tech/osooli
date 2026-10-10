<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What an owner may see of a parcel they hold: their own data only.
 *
 * A parcel keeps the deeds of everyone who ever held it. To its present owner
 * the earlier ones are other people's records — their names, their national
 * ids, the scans of their deeds — so the portal and the mobile API show a
 * parcel through this class: only the deeds the owner is on, and no deed scan
 * that belongs to someone else's deed. Staff screens do not use it.
 */
final class OwnerVisibility
{
    /** Cuts an already-found parcel down to the owner's own deeds and documents. */
    public static function narrow(Parcel $parcel, Owner $owner): Parcel
    {
        $parcel->loadMissing(['deeds.owners', 'photos']);

        // Read before the other deeds are dropped: who it came from, by name only.
        $parcel->setRelation('previousHolders', self::previousHolders($parcel, $owner));

        $own = $parcel->deeds
            ->filter(fn (Deed $deed): bool => $deed->owners->contains('id', $owner->getKey()))
            ->values();
        $ownIds = $own->pluck('id');

        $parcel->setRelation('deeds', $own);
        $parcel->setRelation('photos', $parcel->photos
            ->filter(fn (ParcelPhoto $photo): bool => $photo->deed_id === null || $ownIds->contains($photo->deed_id))
            ->values());

        return $parcel;
    }

    /**
     * The owners on the deed just before the viewer's first one — the people
     * the parcel came from. Their names only; nothing else of theirs is shown.
     *
     * @return Collection<int, Owner>
     */
    private static function previousHolders(Parcel $parcel, Owner $owner): Collection
    {
        $firstOwn = $parcel->deeds->filter(fn (Deed $deed): bool => $deed->owners->contains('id', $owner->getKey()))->min('id');

        $before = $parcel->deeds
            ->filter(fn (Deed $deed): bool => $firstOwn !== null && $deed->id < $firstOwn && ! $deed->owners->contains('id', $owner->getKey()))
            ->sortByDesc('id')
            ->first();

        return $before === null ? new Collection : $before->owners->map(fn (Owner $o): Owner => (new Owner)->forceFill(['name' => $o->name]))->values();
    }

    /**
     * Documents on the owner's parcels, minus scans of deeds they are not on.
     *
     * @param  Builder<ParcelPhoto>  $query
     * @return Builder<ParcelPhoto>
     */
    public static function documents(Builder $query, Owner $owner): Builder
    {
        return $query
            ->whereIn('parcel_id', $owner->parcels()->select('parcels.id'))
            ->where(fn (Builder $q) => $q
                ->whereNull('deed_id')
                ->orWhereIn('deed_id', DB::table('deed_owners')->where('owner_id', $owner->getKey())->select('deed_id')));
    }

    public static function canSeeDocument(Owner $owner, ParcelPhoto $photo): bool
    {
        return self::documents(ParcelPhoto::query()->whereKey($photo->getKey()), $owner)->exists();
    }
}
