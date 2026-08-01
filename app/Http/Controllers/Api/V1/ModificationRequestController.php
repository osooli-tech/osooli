<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreModificationRequest;
use App\Http\Resources\ModificationRequestResource;
use App\Models\ModificationRequest;
use App\Services\Owner\ModificationRequestCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModificationRequestController extends ApiController
{
    /** Requests raised by the signed-in owner. */
    public function index(Request $request): JsonResponse
    {
        $requests = ModificationRequest::where('requested_by', $this->owner()->getKey())
            ->with('parcel')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->respondPaginated(
            $requests,
            ModificationRequestResource::collection($requests->getCollection())->resolve()
        );
    }

    public function store(StoreModificationRequest $request, ModificationRequestCreator $creator): JsonResponse
    {
        $owner = $this->owner();

        // Looking the parcel up through the owner rejects ids they do not hold,
        // without confirming whether the parcel exists at all.
        $parcel = $owner->parcels()->find((int) $request->validated('parcel_id'));

        if ($parcel === null) {
            return $this->respondInvalid('parcel_id', __('api.parcel_not_owned'));
        }

        $created = $creator->create(
            $owner,
            $parcel,
            (string) $request->validated('field_name'),
            (string) $request->validated('new_value'),
            $request->validated('notes'),
        );

        return $this->respondCreated(array_merge(
            (new ModificationRequestResource($created))->resolve(),
            ['message' => __('api.modification_request_created')],
        ));
    }
}
