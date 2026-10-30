<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Requests\Listings\StoreInquiryRequest;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Models\ParcelListing;
use App\Services\Listings\ParcelListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parcels owners offer for sale, for the mobile app: the same rules as the
 * portal, through the same service. A listing the owner may not see answers
 * 404, never 403.
 */
class ListingController extends ApiController
{
    public function __construct(private readonly ParcelListingService $listings) {}

    /** Approved listings of other owners that this owner may see. */
    public function index(Request $request): JsonResponse
    {
        $page = $this->listings->market($this->owner())->paginate($this->perPage($request))->withQueryString();

        return $this->respondPaginated($page, $page->getCollection()->map(fn (ParcelListing $l): array => $this->item($l))->all());
    }

    public function show(int $listing): JsonResponse
    {
        $found = $this->listings->market($this->owner())->find($listing);

        return $found === null
            ? $this->respondError(__('api.not_found'), Response::HTTP_NOT_FOUND)
            : $this->respond($this->item($found));
    }

    /** The signed-in owner's own listings, whatever their status. */
    public function mine(): JsonResponse
    {
        $mine = ParcelListing::query()
            ->where('owner_id', $this->owner()->getKey())
            ->with('parcel:id,parcel_no')
            ->latest('id')
            ->get()
            ->map(fn (ParcelListing $l): array => $this->own($l))
            ->all();

        return $this->respondCollection($mine);
    }

    /** The buyer asks Sukooki to put them in touch; neither side sees the other's number. */
    public function interest(StoreInquiryRequest $request, int $listing): JsonResponse
    {
        $found = $this->listings->market($this->owner())->find($listing);

        if ($found === null) {
            return $this->respondError(__('api.not_found'), Response::HTTP_NOT_FOUND);
        }

        $inquiry = $this->listings->inquire($this->owner(), $found, $request->validated('note'));

        return $this->respondCreated(['status' => $inquiry->status->value, 'message' => __('listings.interest_sent')]);
    }

    public function store(StoreListingRequest $request, int $parcel): JsonResponse
    {
        $found = $this->parcels()->base()->find($parcel);

        if ($found === null) {
            return $this->respondError(__('api.parcel_not_owned'), Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listings->request($this->owner(), $found, (string) $request->validated('contact_phone'));
        $listing->setRelation('parcel', $found);

        return $this->respondCreated([...$this->own($listing), 'message' => __('listings.requested')]);
    }

    public function destroy(int $listing): JsonResponse
    {
        $found = ParcelListing::query()
            ->where('owner_id', $this->owner()->getKey())
            ->whereIn('status', ListingStatus::openValues())
            ->find($listing);

        if ($found === null) {
            return $this->respondError(__('api.not_found'), Response::HTTP_NOT_FOUND);
        }

        $this->listings->withdraw($found, owner: $this->owner());

        return $this->respondMessage(__('listings.withdrawn'));
    }

    /** @return array<string, mixed> */
    private function item(ParcelListing $listing): array
    {
        $shown = $this->listings->present($listing);
        $shown['geometry'] = $shown['geojson'] === null ? null : json_decode($shown['geojson'], true);
        unset($shown['geojson']);

        return $shown;
    }

    /** @return array<string, mixed> */
    private function own(ParcelListing $listing): array
    {
        return [
            'id' => $listing->id,
            'parcel_id' => $listing->parcel_id,
            'parcel_no' => $listing->parcel?->parcel_no,
            'status' => $listing->status->value,
            'status_label' => $listing->status->label(),
            'contact_phone' => $listing->contact_phone,
            'rejection_reason' => $listing->status === ListingStatus::Rejected ? $listing->rejection_reason : null,
            'created_at' => $listing->created_at?->toIso8601String(),
        ];
    }
}
