<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\ParcelPhoto;
use App\Support\DocumentVault;
use App\Support\OwnerVisibility;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
    public function download(Request $request, int $document): Response
    {
        $photo = ParcelPhoto::whereKey($document)->firstOrFail();

        // On a parcel the owner holds now, and not the scan of a deed they are
        // not on: neither a former owner nor the next one reads the other's deed.
        abort_unless(OwnerVisibility::canSeeDocument($this->owner(), $photo), 404);

        // Opened by the vault (files are encrypted at rest); a missing file is a 404, not a logged download.
        $response = DocumentVault::response($photo, download: true);

        $this->recordDownload($photo, $request);

        return $response;
    }

    /** Every document access is auditable, same as the dashboard. */
    private function recordDownload(ParcelPhoto $photo, Request $request): void
    {
        AuditLog::create([
            // Mobile downloads are made by an owner, who is not a dashboard user.
            'user_id' => null,
            'owner_id' => $this->owner()->getKey(),
            'action' => 'download',
            'target_type' => 'document',
            'target_id' => $photo->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
