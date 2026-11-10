<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Parcel;

/**
 * How much of a parcel each owner holds, read from the deed that holds the
 * parcel today (Parcel::heldDeed) — the same deed that decides whether an
 * owner holds the parcel at all (Owner::parcels()).
 *
 * A recorded share is used, clamped to 0..100. A missing one means the share
 * is only written as prose inside the deed, so the holders split it equally.
 * An owner who is not on the held deed holds nothing, even if an older deed
 * still names them.
 */
class OwnerHoldings
{
    /** Eager-load these on a parcel query before asking for shares. */
    public const RELATIONS = ['heldDeed.deedOwners'];

    /** The owner's fraction (0..1) of the parcel. */
    public function share(Parcel $parcel, int $ownerId): float
    {
        return $this->shares($parcel)[$ownerId] ?? 0.0;
    }

    /**
     * Every holder's fraction of the parcel.
     *
     * @return array<int, float> owner id => share (0..1)
     */
    public function shares(Parcel $parcel): array
    {
        $parcel->loadMissing(self::RELATIONS);
        $holders = $parcel->heldDeed?->deedOwners ?? collect();

        if ($holders->isEmpty()) {
            return [];
        }

        $equal = 1.0 / $holders->count();
        $shares = [];

        foreach ($holders as $holder) {
            $recorded = $holder->ownership_share;
            $shares[(int) $holder->owner_id] = $recorded === null
                ? $equal
                : min(100.0, max(0.0, (float) $recorded)) / 100;
        }

        return $shares;
    }
}
