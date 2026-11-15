<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PortalPermission;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ParcelDetailResource;
use App\Http\Resources\ParcelListResource;
use App\Http\Resources\ParcelMapResource;
use App\Queries\OwnerParcelQuery;
use App\Support\OwnerVisibility;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelController extends ApiController
{
    /** Parcels the signed-in owner holds. */
    public function index(Request $request): JsonResponse
    {
        $parcels = (new OwnerParcelQuery($this->readingOwner($request)))
            ->filtered($request->only(['search', 'asset_type', 'city', 'district']))
            ->with(['plan.district.city', 'latestDeed', 'heldDeed.owners'])
            ->withCount('photos')
            ->orderBy('parcel_no')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->respondPaginated(
            $parcels,
            ParcelListResource::collection($parcels->getCollection())->resolve()
        );
    }

    /** Full detail for one owned parcel. */
    public function show(int $parcel): ParcelDetailResource
    {
        $model = $this->parcels()->base()
            ->with([
                'plan.district.city',
                'deeds.owners',
                'boundary.engineeringOffice',
                'surveyDecisions',
                'photos',
            ])
            ->findOrFail($parcel);
        // Earlier holders' deeds, names and deed scans are not this owner's to see.
        OwnerVisibility::narrow($model, $this->owner());
        // Nor are any documents, when staff turned them off for this account on this owner.
        if (! app(PortalContext::class)->can(PortalPermission::Documents)) {
            $model->setRelation('photos', $model->photos->take(0));
        }

        return new ParcelDetailResource($model);
    }

    /** GeoJSON feed for the map screen. */
    public function map(Request $request): JsonResponse
    {
        $parcels = (new OwnerParcelQuery($this->readingOwner($request)))->withGeometry()
            ->with(['plan.district.city', 'latestDeed.owners', 'heldDeed.owners'])
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => ParcelMapResource::collection($parcels)->resolve(),
        ]);
    }

    /** Documents attached to an owned parcel. */
    public function documents(int $parcel): JsonResponse
    {
        $model = $this->parcels()->base()->with('photos')->findOrFail($parcel);
        OwnerVisibility::narrow($model, $this->owner());

        return $this->respondCollection(
            DocumentResource::collection($model->photos)->resolve()
        );
    }
}
