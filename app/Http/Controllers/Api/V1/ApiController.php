<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponder;
use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Queries\OwnerParcelQuery;
use App\Support\Portal\PortalContext;
use Illuminate\Http\Request;

/**
 * Shared base for the mobile API: the response envelope, the signed-in owner,
 * and the owner-scoped parcel query.
 */
abstract class ApiController extends Controller
{
    use ApiResponder;

    /** The owner behind the current Sanctum token. */
    protected function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = auth()->user();

        return $owner;
    }

    /** The header with which the app asks to read as the whole account. */
    public const READ_SCOPE_HEADER = 'X-Read-Scope';

    /**
     * For read endpoints only: with `X-Read-Scope: account`, an account that
     * opens several owners reads all their land at once, as the web portal
     * does (PortalContext::readingOwner). Without the header — older apps —
     * the token's owner alone, as before. Never used where something is done:
     * acting stays with the one owner who holds the parcel.
     */
    protected function readingOwner(Request $request): Owner
    {
        if ($request->header(self::READ_SCOPE_HEADER) === 'account') {
            return app(PortalContext::class)->readingOwner() ?? $this->owner();
        }

        return $this->owner();
    }

    /** Parcel queries restricted to the signed-in owner. */
    protected function parcels(): OwnerParcelQuery
    {
        return new OwnerParcelQuery($this->owner());
    }

    /** Page size, clamped so a client cannot ask for an unbounded page. */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }
}
