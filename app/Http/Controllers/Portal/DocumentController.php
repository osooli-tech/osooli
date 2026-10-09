<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Queries\OwnerParcelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-portal equivalent of the internal DocumentController::download() —
 * kept separate because that one authorises via a `users` permission and
 * writes the audit row against `user_id`, neither of which applies to the
 * `owner` guard.
 */
class DocumentController extends Controller
{
    /** A site photo shown inline; 404 unless it sits on one of the owner's parcels. Not audited — it is a page view. */
    public function preview(ParcelPhoto $photo): StreamedResponse
    {
        abort_unless(
            $photo->isGalleryImage() && (new OwnerParcelQuery($this->owner()))->base()->whereKey($photo->parcel_id)->exists(),
            404
        );

        $location = $photo->storageLocation();
        $disk = Storage::disk($location['disk']);
        abort_unless($disk->exists($location['path']), 404);

        return $disk->response($location['path'], null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function download(Request $request, ParcelPhoto $photo): StreamedResponse
    {
        $owner = $this->owner();

        // Mirrors OwnerScope::canSeeParcel for the internal download route: a
        // document id guessed from another parcel is a 404, not a 403 — that
        // way whether it even exists is not information a stranger gets.
        abort_unless(
            (new OwnerParcelQuery($owner))->base()->whereKey($photo->parcel_id)->exists(),
            404
        );

        $location = $photo->storageLocation();
        $disk = Storage::disk($location['disk']);

        abort_unless($disk->exists($location['path']), 404);

        AuditLog::create([
            'owner_id' => $owner->id,
            'action' => 'download',
            'target_type' => 'document',
            'target_id' => $photo->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $disk->download($location['path'], $photo->downloadName());
    }

    /** The list itself is the Portal\DocumentIndex Livewire component, embedded in the view. */
    public function list(): View
    {
        return view('portal.documents.index');
    }

    /** Documents and gallery images of one of the owner's parcels, for the dashboard map's side panel. */
    public function index(int $parcel): JsonResponse
    {
        /** @var Parcel $found */
        $found = (new OwnerParcelQuery($this->owner()))->base()->with('photos')->findOrFail($parcel);

        $isGalleryImage = fn (ParcelPhoto $photo): bool => $photo->isGalleryImage();

        return response()->json([
            'documents' => $found->photos->reject($isGalleryImage)->values()->map(fn (ParcelPhoto $photo): array => [
                'id' => $photo->id,
                'type' => $photo->photo_type ? __('documents.photo_types.'.$photo->photo_type->value) : null,
                'download_url' => route('portal.documents.download', $photo),
            ]),
            'images' => $found->photos->filter($isGalleryImage)->values()->map(fn (ParcelPhoto $photo): array => [
                'id' => $photo->id,
                'url' => route('portal.documents.preview', $photo),
            ]),
        ]);
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
