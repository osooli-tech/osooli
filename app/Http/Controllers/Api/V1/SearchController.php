<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SearchResultResource;
use App\Queries\OwnerParcelQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends ApiController
{
    /** How many hits the search screen shows before the user refines the term. */
    private const RESULT_LIMIT = 50;

    /**
     * Searches the owner's own holdings by deed no., parcel no., plan or owner name.
     *
     * The query is owner-scoped, so searching by owner name still never
     * surfaces anyone else's parcels.
     */
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->string('q'));

        if ($term === '') {
            return $this->respondCollection([]);
        }

        $parcels = (new OwnerParcelQuery($this->readingOwner($request)))
            ->searchBy((string) $request->string('type', 'deed'), $term)
            ->with(['plan.district.city', 'latestDeed', 'heldDeed.owners'])
            ->limit(self::RESULT_LIMIT)
            ->get();

        return $this->respondCollection(
            SearchResultResource::collection($parcels)->resolve()
        );
    }
}
