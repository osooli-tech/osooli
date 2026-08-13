<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DocumentResource;
use App\Http\Resources\ParcelDetailResource;
use App\Http\Resources\ParcelListResource;
use App\Http\Resources\ParcelMapResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelController extends ApiController
{
    /** Parcels the signed-in owner holds. */
    public function index(Request $request): JsonResponse
    {
        $parcels = $this->parcels()
            ->filtered($request->only(['search', 'asset_type', 'city', 'district']))
            ->with(['plan.district.city', 'latestDeed'])
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

        return new ParcelDetailResource($model);
    }

    /** GeoJSON feed for the map screen. */
    public function map(): JsonResponse
    {
        $parcels = $this->parcels()->withGeometry()
            ->with(['plan.district.city', 'latestDeed.owners'])
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

        return $this->respondCollection(
            DocumentResource::collection($model->photos)->resolve()
        );
    }
}
