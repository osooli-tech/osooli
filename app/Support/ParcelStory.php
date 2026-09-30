<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DeedStatus;
use App\Models\ModificationRequest;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use Illuminate\Support\Collection;

/**
 * A parcel's recorded events on one timeline: deeds issued (Hijri dates,
 * converted through Umm al-Qura), documents filed and the owner's change
 * requests. Only dated records appear — nothing is placed on a guessed date.
 */
final class ParcelStory
{
    /**
     * @param  Collection<int, ModificationRequest>  $requests  the owner's own requests on this parcel
     * @return list<array{at: int, icon: string, tone: string, title: string, detail: string|null, hijri: string|null}>
     */
    public static function for(Parcel $parcel, Collection $requests): array
    {
        $events = [];

        foreach ($parcel->deeds as $deed) {
            $at = HijriDate::toTimestamp($deed->deed_date_hijri);
            if ($at === null) {
                continue;
            }
            $current = $deed->deed_status === DeedStatus::Updated->value;
            $events[] = [
                'at' => $at,
                'icon' => 'workspace_premium',
                'tone' => $current ? 'secondary' : 'outline',
                'title' => __('portal.story_deed', ['no' => $deed->deed_no ?? '—']),
                'detail' => $deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : null,
                'hijri' => $deed->deed_date_hijri,
            ];
        }

        foreach ($parcel->photos as $photo) {
            /** @var ParcelPhoto $photo */
            if ($photo->created_at === null) {
                continue;
            }
            $events[] = [
                'at' => $photo->created_at->getTimestamp(),
                'icon' => 'upload_file',
                'tone' => 'primary',
                'title' => __('portal.story_document', ['type' => $photo->photo_type ? __('documents.photo_types.'.$photo->photo_type->value) : '—']),
                'detail' => $photo->original_name,
                'hijri' => null,
            ];
        }

        foreach ($requests as $request) {
            $events[] = [
                'at' => $request->created_at->getTimestamp(),
                'icon' => 'edit_note',
                'tone' => 'tertiary',
                'title' => __('portal.story_request', ['field' => $request->fieldLabel()]),
                'detail' => ($request->old_value ?? '—').' ← '.$request->new_value,
                'hijri' => null,
            ];
            if ($request->resolved_at !== null) {
                $events[] = [
                    'at' => $request->resolved_at->getTimestamp(),
                    'icon' => 'task_alt',
                    'tone' => 'secondary',
                    'title' => __('portal.story_request_resolved', ['field' => $request->fieldLabel()]),
                    'detail' => __('modification_requests.status.'.$request->status->value),
                    'hijri' => null,
                ];
            }
        }

        usort($events, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $events;
    }
}
