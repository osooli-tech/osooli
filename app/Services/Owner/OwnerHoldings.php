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
 * is only written as prose inside the deed, so the holders without one split
 * equally what the recorded shares leave. Recorded shares that add up past
 * the whole are scaled down, so a parcel is never held more than once over.
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
        $holders = $parcel->heldDeed->deedOwners ?? collect();

        if ($holders->isEmpty()) {
            return [];
        }

        $shares = [];
        $blank = [];

        foreach ($holders as $holder) {
            $recorded = $holder->ownership_share;

            if ($recorded === null) {
                $blank[] = (int) $holder->owner_id;

                continue;
            }

            $shares[(int) $holder->owner_id] = min(100.0, max(0.0, (float) $recorded)) / 100;
        }

        // Those without a recorded share split what the others leave.
        $left = max(0.0, 1.0 - array_sum($shares));
        foreach ($blank as $ownerId) {
            $shares[$ownerId] = $left / count($blank);
        }

        // Recorded shares past the whole are read as proportions of it.
        $total = array_sum($shares);
        if ($total > 1.0) {
            $shares = array_map(static fn (float $share): float => $share / $total, $shares);
        }

        return $shares;
    }
}
