<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DocumentResource;
use App\Models\PortalSetting;
use App\Services\Owner\LinkedParcelsService;
use App\Services\Owner\PortfolioCards;
use App\Support\ParcelFrontage;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner's estate as the web home shows it: the portfolios their land is
 * split into, and the parcels they handed on but still stand over (parent
 * owner) — with only what the administrator's switches allow of those.
 */
class HoldingsController extends ApiController
{
    /** The portfolio cards; one per owner when reading as a whole account. */
    public function portfolios(Request $request, PortfolioCards $cards): JsonResponse
    {
        $owner = $this->readingOwner($request);

        return $this->respondCollection($cards->for($owner, app(PortalContext::class)->account()));
    }

    /** Linked parcels by holder, after the owner's own holdings as a portfolio of theirs. */
    public function linked(LinkedParcelsService $linked): JsonResponse
    {
        $owner = $this->owner();
        $groups = $linked->groups($owner);

        if ($groups !== [] && ($own = $linked->ownGroup($owner)) !== null) {
            array_unshift($groups, $own);
        }

        return $this->respondCollection($groups);
    }

    /** One linked parcel; 404 for any parcel not under this owner. */
    public function linkedShow(int $parcel, LinkedParcelsService $linked): JsonResponse
    {
        $found = $linked->find($this->owner(), $parcel);
        $deed = $found->heldDeed;
        $area = $deed?->deed_area === null ? null : (float) $deed->deed_area;
        $showValue = PortalSetting::allows(PortalSetting::SHOW_VALUE);
        $geometry = $found->getAttribute('geom_json');

        return $this->respond([
            'id' => $found->id,
            'parcel_no' => $found->parcel_no,
            'plan_no' => $found->plan?->plan_no,
            'district' => $found->plan?->district?->name_ar,
            'city' => $found->plan?->district?->city?->name_ar,
            'asset_type' => $found->asset_type,
            'holders' => $deed?->owners->pluck('name')->values()->all() ?? [],
            'area' => $area,
            'value' => $showValue ? ($found->parcel_price !== null ? (float) $found->parcel_price : ($found->m_price === null || $area === null ? null : (float) $found->m_price * $area)) : null,
            'frontage' => ParcelFrontage::read($found->boundary),
            'deed' => PortalSetting::allows(PortalSetting::SHOW_DEED) && $deed !== null ? [
                'deed_no' => $deed->deed_no,
                'deed_date_hijri' => $deed->deed_date_hijri,
                'deed_status' => $deed->deed_status,
                'deed_class' => $deed->deed_class,
            ] : null,
            'geometry' => $geometry === null ? null : json_decode((string) $geometry, true),
            'documents' => DocumentResource::collection($linked->documents($found))->resolve(),
        ]);
    }
}
