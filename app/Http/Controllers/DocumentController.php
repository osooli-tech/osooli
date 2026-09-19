<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ParcelPhoto;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Stream a document to an authorised user.
     *
     * This used to redirect to the file's public URL. That logged the access
     * and then handed the visitor a link the web server answered on its own —
     * no session, no permission check, no owner scoping, and shareable to
     * anyone. Reading the bytes here instead means every one of those checks
     * actually applies to the file, not merely to the click that preceded it.
     */
    public function download(Request $request, ParcelPhoto $photo): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        // The route already carries can:documents.download; repeated here so
        // the guarantee does not depend on how the controller is reached.
        abort_unless($user?->can('documents.download'), 403);

        // A restricted user sees only their own owners' parcels everywhere
        // else; a document id guessed from another parcel must not be the way
        // around that. 404, not 403: whether the document exists is itself
        // information they are not entitled to.
        abort_unless(OwnerScope::canSeeParcel($user, $photo->parcel_id), 404);

        $location = $photo->storageLocation();
        $disk = Storage::disk($location['disk']);

        // A row whose file is missing is a broken link, not a server error.
        abort_unless($disk->exists($location['path']), 404);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'download',
            'target_type' => 'document',
            'target_id' => $photo->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $disk->download($location['path'], $photo->downloadName());
    }
}
