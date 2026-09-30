<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\ModificationRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateProfileRequest;
use App\Models\AuditLog;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\ParcelPhoto;
use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(): View
    {
        $owner = $this->owner();
        $parcelIds = $owner->parcels()->pluck('parcels.id');
        $summary = (new OwnerStatisticsService($owner))->summary();

        return view('portal.profile.show', [
            'owner' => $owner,
            'stats' => [
                'parcels' => $parcelIds->count(),
                'deeds' => $summary['deeds_active'] + $summary['deeds_expired'],
                'documents' => ParcelPhoto::whereIn('parcel_id', $parcelIds)->count(),
                'pending_requests' => $owner->modificationRequests()
                    ->where('status', ModificationRequestStatus::Pending->value)
                    ->count(),
            ],
            'recentRequests' => ModificationRequest::where('requested_by', $owner->getKey())
                ->with('parcel:id,parcel_no')
                ->latest('id')
                ->limit(5)
                ->get(),
            'recentDownloads' => AuditLog::where('owner_id', $owner->getKey())
                ->where('action', 'download')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->owner()->update($request->validated());

        return redirect()->route('portal.profile')->with('status', __('portal.profile_saved'));
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
