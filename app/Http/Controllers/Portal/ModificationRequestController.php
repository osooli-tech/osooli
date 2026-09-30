<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreModificationRequest;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Queries\OwnerParcelQuery;
use App\Services\Owner\ModificationRequestCreator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** An owner's request to change a parcel field — recorded for review, never applied directly. */
class ModificationRequestController extends Controller
{
    public function index(): View
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return view('portal.modification-requests.index', [
            'statusCounts' => ModificationRequest::where('requested_by', $owner->getKey())
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'requests' => ModificationRequest::where('requested_by', $owner->getKey())
                ->with('parcel:id,parcel_no')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function store(StoreModificationRequest $request, int $parcel, ModificationRequestCreator $creator): RedirectResponse
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        $found = (new OwnerParcelQuery($owner))->base()->findOrFail($parcel);

        $creator->create(
            $owner,
            $found,
            (string) $request->validated('field_name'),
            (string) $request->validated('new_value'),
            $request->validated('notes'),
        );

        return redirect()
            ->route('portal.parcels.show', $found)
            ->with('status', __('portal.modification_request_sent'));
    }
}
