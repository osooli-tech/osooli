<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\ParcelPhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    /**
     * Redirects to the stored file.
     *
     * Only documents on the owner's own parcels resolve; anything else 404s
     * rather than revealing that the document exists.
     */
    public function download(Request $request, int $document): RedirectResponse
    {
        $photo = ParcelPhoto::whereKey($document)
            ->whereHas(
                'parcel.deeds.owners',
                fn (Builder $query) => $query->whereKey($this->owner()->getKey())
            )
            ->firstOrFail();

        $this->recordDownload($photo, $request);

        return redirect()->away($photo->photo_url);
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
