<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Restricts a user to only the parcels/deeds/owners belonging to a fixed set
 * of owners — for showing one client's own project to them without exposing
 * the rest of the platform's data.
 *
 * Deliberately not a global Eloquent scope: most of the read paths this
 * needs to cover (dashboard KPIs, the map GeoJSON feed, owner listings) are
 * raw SQL, not Eloquent queries, so a model-level scope would silently miss
 * them. Every call site applies this explicitly instead, which is more
 * lines but never gives a false sense of "already covered."
 *
 * A user with no rows in user_owner_scopes is unrestricted — every method
 * here returns null for one, meaning "no filter, show everything," never an
 * empty array (which would instead mean "show nothing").
 */
class OwnerScope
{
    /** @var array<int, list<int>|null> per-user-id memoised owner ids, within one request */
    private static array $ownerIdsCache = [];

    /** @var array<int, list<int>|null> per-user-id memoised parcel ids, within one request */
    private static array $parcelIdsCache = [];

    /** @return list<int>|null null means unrestricted */
    public static function ownerIds(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! array_key_exists($user->id, self::$ownerIdsCache)) {
            $ids = $user->scopedOwners()->pluck('owners.id')->all();
            self::$ownerIdsCache[$user->id] = $ids === [] ? null : $ids;
        }

        return self::$ownerIdsCache[$user->id];
    }

    /** @return list<int>|null null means unrestricted */
    public static function parcelIds(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! array_key_exists($user->id, self::$parcelIdsCache)) {
            $ownerIds = self::ownerIds($user);

            self::$parcelIdsCache[$user->id] = $ownerIds === null ? null : DB::table('deed_owners')
                ->join('deeds', 'deeds.id', '=', 'deed_owners.deed_id')
                ->whereIn('deed_owners.owner_id', $ownerIds)
                ->distinct()
                ->pluck('deeds.parcel_id')
                ->all();
        }

        return self::$parcelIdsCache[$user->id];
    }

    public static function isRestricted(?User $user): bool
    {
        return self::ownerIds($user) !== null;
    }

    /** Whether a restricted user may see this specific parcel. Always true when unrestricted. */
    public static function canSeeParcel(?User $user, int $parcelId): bool
    {
        $ids = self::parcelIds($user);

        return $ids === null || in_array($parcelId, $ids, true);
    }
}
