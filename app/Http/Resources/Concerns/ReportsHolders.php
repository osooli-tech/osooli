<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Enums\PortalPermission;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\PortalAccountOwner;
use App\Support\Portal\PortalContext;
use Illuminate\Http\Request;

/**
 * When the app reads as a whole account, each parcel says which of the
 * account's owners hold it, so the app can act as one of them before opening
 * it. Only the account's own owners are named, by id; never anyone else.
 */
trait ReportsHolders
{
    /** @return list<int>|null null when the viewer reads as one owner: nothing to add */
    protected function holderIdsFor(Parcel $parcel, Request $request): ?array
    {
        $viewer = $request->user();

        if (! $viewer instanceof Owner || ! $viewer->standsForMany()) {
            return null;
        }

        $onDeed = $parcel->heldDeed?->owners->pluck('id')->map(fn ($id): int => (int) $id)->all() ?? [];

        return array_values(array_intersect($viewer->holderIds(), $onDeed));
    }

    /**
     * Of the account's owners, those whose grant lets the account read their
     * documents — so a shared list never offers the scan of an owner who
     * withheld them. Read once per request.
     *
     * @return list<int>
     */
    protected function documentReaderIds(Request $request): array
    {
        // Kept on the request itself, so nothing outlives it.
        $cached = $request->attributes->get('portal.document_reader_ids');
        if (is_array($cached)) {
            return $cached;
        }

        $ids = app(PortalContext::class)->account()?->usableGrants()->with('account')->get()
            ->filter(fn (PortalAccountOwner $grant): bool => $grant->allows(PortalPermission::Documents))
            ->pluck('owner_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all() ?? [];
        $request->attributes->set('portal.document_reader_ids', $ids);

        return $ids;
    }
}
