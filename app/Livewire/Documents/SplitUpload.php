<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Enums\PhotoType;
use App\Models\ParcelPhoto;
use App\Models\User;
use App\Support\Concerns\WritesSafely;
use App\Support\DatabaseEnum;
use App\Support\Import\MapPageMatcher;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * One PDF holding documents for many parcels — a map series exported from
 * ArcGIS Pro, a scan of several survey sheets — split page by page and
 * filed against the parcel each page belongs to.
 *
 * The browser renders the pages (pdf.js) and sends each page's text here;
 * where the text names a parcel and a plan on record (MapPageMatcher), the
 * page is matched on its own. The person checks, groups pages that belong
 * together, fixes any match, and the browser cuts each part into a PDF of
 * its own (pdf-lib) and sends it here one at a time. Each part is stored as
 * any uploaded document is: on the private disk, pending review, audited.
 */
class SplitUpload extends Component
{
    use WithFileUploads;
    use WritesSafely;

    /** One part of the file, uploaded by the browser just before savePiece(). */
    public ?TemporaryUploadedFile $piece = null;

    /** Parcels returned per search. */
    private const RESULTS = 20;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('documents.upload'), 403);
    }

    /**
     * The parcel a page names in its text, if exactly one parcel on record
     * fits best; null to leave the page for the person to match.
     *
     * @return array{id: int, label: string}|null
     */
    public function matchPage(string $text): ?array
    {
        abort_unless(Auth::user()?->can('documents.upload'), 403);

        $text = mb_substr($text, 0, 20000);

        // A GEO ID or a "parcel-plan" pair on the page settles it outright —
        // what a survey sheet prints, and what OCR reads most reliably.
        $pairs = MapPageMatcher::pairs($text);
        if ($pairs !== []) {
            $keys = array_map(static fn (array $p): string => $p[0].'-'.$p[1], $pairs);
            $byGeo = $this->parcels()->whereIn('p.geo_id', $keys)->limit(3)->get();
            if ($byGeo->count() === 1) {
                return $this->labelled($byGeo)[0];
            }

            $byPair = $this->parcels()
                ->where(function (Builder $q) use ($pairs): void {
                    foreach ($pairs as [$parcel, $plan]) {
                        $q->orWhere(fn (Builder $x) => $x->where('p.parcel_no', $parcel)->where('pl.plan_no', $plan));
                    }
                })
                ->limit(3)
                ->get();
            if ($byPair->count() === 1) {
                return $this->labelled($byPair)[0];
            }
        }

        $scores = MapPageMatcher::scores($text);
        $numbers = array_slice(array_unique(array_merge(array_keys($scores['parcel']), array_keys($scores['plan']))), 0, 30);
        if ($numbers === []) {
            return null;
        }

        $rows = $this->parcels()
            ->whereIn('p.parcel_no', $numbers)
            ->whereIn('pl.plan_no', $numbers)
            ->whereColumn('p.parcel_no', '!=', 'pl.plan_no')
            ->limit(50)
            ->get();

        $best = null;
        $bestScore = -1;
        $tied = false;
        foreach ($rows as $row) {
            $score = ($scores['parcel'][(string) $row->parcel_no] ?? 0) * 2 + ($scores['plan'][(string) $row->plan_no] ?? 0);
            if ($score > $bestScore) {
                [$best, $bestScore, $tied] = [$row, $score, false];
            } elseif ($score === $bestScore) {
                $tied = true;
            }
        }

        return $best === null || $tied ? null : $this->labelled([$best])[0];
    }

    /**
     * Parcels matching what was typed: "131 623" is parcel 131 of plan 623;
     * anything else is looked for in the parcel number, the GEO ID, the plan
     * number and the owners' names.
     *
     * @return list<array{id: int, label: string}>
     */
    public function searchParcels(string $term): array
    {
        abort_unless(Auth::user()?->can('documents.upload'), 403);

        $term = trim(mb_substr($term, 0, 100));
        if ($term === '') {
            return [];
        }

        $query = $this->parcels();
        $parts = preg_split('/[\s\-\/،,]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 2) {
            // Parcel and plan, in either order.
            $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $x) => $x->where('p.parcel_no', $parts[0])->where('pl.plan_no', $parts[1]))
                ->orWhere(fn (Builder $x) => $x->where('p.parcel_no', $parts[1])->where('pl.plan_no', $parts[0])));
        } else {
            $like = '%'.$term.'%';
            $query->where(fn (Builder $q) => $q
                ->where('p.parcel_no', $term)
                ->orWhere('pl.plan_no', $term)
                ->orWhereLike('p.geo_id', $like)
                ->orWhereExists(fn (Builder $o) => $o->selectRaw('1')
                    ->from('deeds as d')
                    ->join('deed_owners as dow', 'dow.deed_id', '=', 'd.id')
                    ->join('owners as ow', 'ow.id', '=', 'dow.owner_id')
                    ->whereColumn('d.parcel_id', 'p.id')
                    ->whereNull('d.deleted_at')
                    ->whereLike('ow.name', $like)));
        }

        return $this->labelled($query->limit(self::RESULTS)->get());
    }

    /**
     * Every parcel of a plan — offered first when the file's name carries the
     * plan number ("… مخطط 623.pdf").
     *
     * @return list<array{id: int, label: string}>
     */
    public function planParcels(string $planNo): array
    {
        abort_unless(Auth::user()?->can('documents.upload'), 403);

        $planNo = trim(mb_substr($planNo, 0, 50));

        return $planNo === '' ? [] : $this->labelled($this->parcels()->where('pl.plan_no', $planNo)->limit(300)->get());
    }

    /**
     * Store the part just uploaded into $piece against its parcel.
     *
     * @param  array<string, mixed>  $meta  parcel_id, photo_type, pages, source
     * @return array{id: int}
     */
    public function savePiece(array $meta): array
    {
        abort_unless(Auth::user()?->can('documents.upload'), 403);

        $this->validate([
            'piece' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $parcelId = is_numeric($meta['parcel_id'] ?? null) ? (int) $meta['parcel_id'] : 0;
        abort_unless($parcelId > 0 && DB::table('parcels')->where('id', $parcelId)->whereNull('deleted_at')->exists(), 422);
        abort_unless(OwnerScope::canSeeParcel($this->user(), $parcelId), 403);

        $type = (string) ($meta['photo_type'] ?? PhotoType::BoundarySurvey->value);
        abort_unless(in_array($type, DatabaseEnum::for('photo_type'), true), 422);

        $pages = preg_replace('/[^0-9,\-]/', '', (string) ($meta['pages'] ?? '')) ?? '';
        $source = pathinfo(basename((string) ($meta['source'] ?? 'document.pdf')), PATHINFO_FILENAME);

        $path = $this->piece?->store(now()->format('Y/m'), ParcelPhoto::PRIVATE_DISK);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException(__('documents.store_failed'));
        }

        $photo = $this->writeSafely('document.upload', 'document', null, fn (): ParcelPhoto => ParcelPhoto::create([
            'parcel_id' => $parcelId,
            'photo_url' => $path,
            'photo_type' => $type,
            // "623_image — ص 5.pdf": the file it came from, and which pages.
            'original_name' => mb_substr($source, 0, 180).($pages !== '' ? ' — ص '.$pages : '').'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->piece->getSize(),
            'storage_disk' => ParcelPhoto::PRIVATE_DISK,
            'status' => ParcelPhoto::STATUS_PENDING,
            'uploaded_by' => Auth::id(),
        ]));

        $this->piece = null;

        return ['id' => (int) $photo->id];
    }

    public function render(): View
    {
        return view('livewire.documents.split-upload', [
            'types' => array_map(
                static fn (string $value): array => ['value' => $value, 'label' => __('documents.photo_types.'.$value)],
                DatabaseEnum::for('photo_type')
            ),
        ]);
    }

    /** Live parcels this user may see, with their plan and district. */
    private function parcels(): Builder
    {
        $allowed = OwnerScope::parcelIds($this->user());

        return DB::table('parcels as p')
            ->leftJoin('plans as pl', 'pl.id', '=', 'p.plan_id')
            ->leftJoin('districts as di', 'di.id', '=', 'pl.district_id')
            ->whereNull('p.deleted_at')
            ->when($allowed !== null, fn (Builder $q) => $q->whereIn('p.id', $allowed ?? []))
            ->orderBy('pl.plan_no')
            ->orderByRaw('LENGTH(p.parcel_no), p.parcel_no')
            ->select(['p.id', 'p.parcel_no', 'p.geo_id', 'pl.plan_no', 'di.name_ar as district']);
    }

    /**
     * "ق 131 — مخطط 623 — العمارية — الأمير فيصل …"
     *
     * @param  iterable<object>  $rows
     * @return list<array{id: int, label: string}>
     */
    private function labelled(iterable $rows): array
    {
        $rows = collect($rows);
        $owners = DB::table('deeds as d')
            ->join('deed_owners as dow', 'dow.deed_id', '=', 'd.id')
            ->join('owners as ow', 'ow.id', '=', 'dow.owner_id')
            ->whereIn('d.parcel_id', $rows->pluck('id'))
            ->whereNull('d.deleted_at')
            ->get(['d.parcel_id', 'ow.name'])
            ->groupBy('parcel_id');

        return $rows->map(fn (object $r): array => [
            'id' => (int) $r->id,
            'label' => implode(' — ', array_filter([
                __('documents.split.parcel_short', ['no' => $r->parcel_no ?? '؟']),
                $r->plan_no !== null ? __('documents.split.plan_short', ['no' => $r->plan_no]) : null,
                $r->district,
                $owners->get($r->id)?->pluck('name')->unique()->take(2)->implode('، '),
                $r->geo_id,
            ])),
        ])->values()->all();
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
