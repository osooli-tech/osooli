<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\StoreListingRequest;
use App\Models\Owner;
use App\Models\ParcelListing;
use App\Queries\OwnerParcelQuery;
use App\Services\Listings\ParcelListingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Parcels owners offer for sale: browsing the ones staff approved, and an
 * owner listing or withdrawing their own. A listing the owner may not see is
 * a plain 404, never a 403 that would confirm it exists.
 */
class ListingController extends Controller
{
    private const PER_PAGE = 12;

    public function __construct(private readonly ParcelListingService $listings) {}

    public function index(): View
    {
        $owner = $this->owner();
        $page = $this->listings->market($owner)->paginate(self::PER_PAGE);

        return view('portal.listings.index', [
            'listings' => $page->getCollection()->map(fn (ParcelListing $l): array => $this->listings->present($l))->all(),
            'page' => $page,
            'mine' => ParcelListing::query()
                ->where('owner_id', $owner->getKey())
                ->with('parcel:id,parcel_no')
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(int $listing): View
    {
        /** @var ParcelListing $found */
        $found = $this->listings->market($this->owner())->findOrFail($listing);

        return view('portal.listings.show', ['listing' => $this->listings->present($found)]);
    }

    public function store(StoreListingRequest $request, int $parcel): RedirectResponse
    {
        $owner = $this->owner();
        $found = (new OwnerParcelQuery($owner))->base()->findOrFail($parcel);

        $this->listings->request($owner, $found, (string) $request->validated('contact_phone'));

        return redirect()->route('portal.parcels.show', $found)->with('listing_status', __('listings.requested'));
    }

    public function destroy(int $listing): RedirectResponse
    {
        $found = ParcelListing::query()
            ->where('owner_id', $this->owner()->getKey())
            ->whereIn('status', ListingStatus::openValues())
            ->findOrFail($listing);

        $this->listings->withdraw($found, owner: $this->owner());

        return back()->with('listing_status', __('listings.withdrawn'));
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
