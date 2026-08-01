<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponder;
use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Queries\OwnerParcelQuery;
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
