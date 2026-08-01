<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DeedListResource;
use App\Queries\OwnerDeedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeedController extends ApiController
{
    /** Deed history across every parcel the signed-in owner holds. */
    public function index(Request $request): JsonResponse
    {
        $deeds = (new OwnerDeedQuery($this->owner()))
            ->filtered($request->only(['status', 'search']))
            ->with(['parcel.plan.district.city', 'parcel.photos'])
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->respondPaginated(
            $deeds,
            DeedListResource::collection($deeds->getCollection())->resolve()
        );
    }
}
