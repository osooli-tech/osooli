<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Parcel;
use App\Queries\OwnerParcelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only parcel screens for the owner portal. Every query starts from
 * OwnerParcelQuery, so a parcel outside the signed-in owner's own holdings
 * is a plain 404, never a 403 that would confirm it exists.
 */
class ParcelController extends Controller
{
    public function index(Request $request): View
    {
        $owner = $this->owner();

        $parcels = (new OwnerParcelQuery($owner))
            ->filtered(['search' => $request->string('search')->toString() ?: null])
            ->with('plan.district.city')
            ->orderBy('parcel_no')
            ->paginate(12)
            ->withQueryString();

        return view('portal.parcels.index', ['parcels' => $parcels]);
    }

    public function show(int $parcel): View
    {
        $owner = $this->owner();

        /** @var Parcel $found */
        $found = (new OwnerParcelQuery($owner))
            ->base()
            ->with(['plan.district.city', 'deeds.deedOwners.owner', 'boundary', 'currentDeed'])
            ->findOrFail($parcel);

        return view('portal.parcels.show', ['parcel' => $found]);
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
