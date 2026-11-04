<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreParcelPhotosRequest;
use App\Models\Owner;
use App\Queries\OwnerParcelQuery;
use App\Services\Owner\OwnerPhotoUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/** An owner adding photos of their own parcel; each waits for staff review before anyone sees it. */
class ParcelPhotoController extends Controller
{
    public function store(StoreParcelPhotosRequest $request, int $parcel, OwnerPhotoUploader $uploader): RedirectResponse
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();
        $found = (new OwnerParcelQuery($owner))->base()->findOrFail($parcel);

        /** @var list<UploadedFile> $files */
        $files = array_values((array) $request->file('photos'));
        $uploader->store($owner, $found, $files, $request->photoType());

        return redirect()->route('portal.parcels.show', $found)->with('photos_status', __('portal.photos_uploaded', ['count' => count($files)]));
    }
}
