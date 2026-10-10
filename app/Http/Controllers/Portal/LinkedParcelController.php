<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\PortalSetting;
use App\Services\Owner\LinkedParcelsService;
use App\Support\ParcelFrontage;
use App\Support\ParcelMassing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Parcels held under the signed-in owner but in someone else's name — read
 * only, grouped by who holds each, and showing no more than the
 * administrator's switches allow.
 */
class LinkedParcelController extends Controller
{
    public function index(LinkedParcelsService $linked): View
    {
        return view('portal.linked.index', [
            'groups' => $linked->groups($this->owner()),
            'showValue' => PortalSetting::allows(PortalSetting::SHOW_VALUE),
        ]);
    }

    public function show(int $parcel, LinkedParcelsService $linked): View
    {
        $found = $linked->find($this->owner(), $parcel);
        $deed = $found->heldDeed;
        $showValue = PortalSetting::allows(PortalSetting::SHOW_VALUE);
        $area = $deed?->deed_area === null ? null : (float) $deed->deed_area;

        return view('portal.linked.show', [
            'parcel' => $found,
            'parcelGeojson' => $found->getAttribute('geom_json'),
            'holders' => $deed?->owners->pluck('name')->all() ?? [],
            'area' => $area,
            'frontage' => ParcelFrontage::read($found->boundary),
            'massing' => ParcelMassing::styleOf($found->asset_type, $deed?->deed_class),
            'deed' => PortalSetting::allows(PortalSetting::SHOW_DEED) ? $deed : null,
            'showValue' => $showValue,
            'value' => $showValue ? ($found->parcel_price ?? ($found->m_price === null || $area === null ? null : (float) $found->m_price * $area)) : null,
            'documents' => $linked->documents($found),
        ]);
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
