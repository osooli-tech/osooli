<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\ParcelPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    /**
     * Streams the stored file.
     *
     * Only documents on the owner's own parcels resolve; anything else 404s
     * rather than revealing that the document exists. A document still
     * awaiting review never resolves either — ParcelPhoto's global scope keeps
     * it out of every query an owner can reach.
     *
     * The route name and URL are unchanged, but the response is now the file
     * itself rather than a 302 to a public URL: the old link bypassed Laravel
     * entirely once issued, so the token check only ever protected the click.
     */
    public function download(Request $request, int $document): StreamedResponse
    {
        $photo = ParcelPhoto::whereKey($document)
            ->whereHas(
                'parcel.deeds.owners',
                fn (Builder $query) => $query->whereKey($this->owner()->getKey())
            )
            ->firstOrFail();

        $location = $photo->storageLocation();
        $disk = Storage::disk($location['disk']);

        abort_unless($disk->exists($location['path']), 404);

        $this->recordDownload($photo, $request);

        return $disk->download($location['path'], $photo->downloadName());
    }

    /** Every document access is auditable, same as the dashboard. */
    private function recordDownload(ParcelPhoto $photo, Request $request): void
    {
        AuditLog::create([
            // Mobile downloads are made by an owner, who is not a dashboard user.
            'user_id' => null,
            'action' => 'download',
            'target_type' => 'document',
            'target_id' => $photo->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
