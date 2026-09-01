<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\ParcelPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Links a ZIP of PDFs to the parcels they belong to.
 *
 * The naming rule is inferred from the majority of entries and reported for
 * confirmation. Files that do not fit the chosen rule are reported unmatched
 * and never written — a silent second guess is how a survey map ends up filed
 * as a deed.
 */
final class DocumentImporter implements Importer
{
    public function __construct(private readonly ArchiveExtractor $extractor) {}

    public function analyze(string $sourcePath): ImportPreview
    {
        [$rule, $matched, $unmatched] = $this->inspect($sourcePath);

        return new ImportPreview(
            totalItems: count($matched) + count($unmatched),
            willCreate: array_sum(array_map(fn (array $m): int => count($m['parcel_ids']), $matched)),
            willUpdate: 0,
            unmatched: count($unmatched),
            details: [
                'rule' => $rule?->value,
                'photo_type' => $rule?->photoType()->value,
                'unmatched_files' => array_slice($unmatched, 0, 50),
            ],
            warnings: $unmatched === [] ? [] : [count($unmatched).' file(s) matched no parcel and will be skipped.'],
        );
    }

    public function commit(string $sourcePath): ImportResult
    {
        [$rule, $matched, $unmatched] = $this->inspect($sourcePath);

        if ($rule === null) {
            return new ImportResult(0, 0, count($unmatched), 0, ['rule' => null], ['No naming rule matched any file in the archive.']);
        }

        $disk = Storage::disk('public');
        $created = 0;

        foreach ($matched as $entry) {
            $stored = $rule->subdirectory().'/'.$entry['filename'];
            $disk->put($stored, (string) file_get_contents($entry['path']));

            foreach ($entry['parcel_ids'] as $parcelId) {
                // parcel_photos.photo_type is a native Postgres enum
                // (photo_type_enum), not a varchar — going through the model
                // (whose photo_type cast is App\Enums\PhotoType) gives Postgres a
                // correctly typed binding. A raw DB::table()->updateOrInsert()
                // with a plain string risks "column photo_type is of type
                // photo_type_enum but expression is of type text". This also
                // avoids updateOrInsert clobbering created_at on an update.
                ParcelPhoto::updateOrCreate(
                    ['parcel_id' => $parcelId, 'photo_type' => $rule->photoType()->value],
                    ['photo_url' => '/storage/'.$stored]
                );
                $created++;
            }
        }

        return new ImportResult(
            created: $created,
            updated: 0,
            skipped: count($unmatched),
            errors: 0,
            details: [
                'rule' => $rule->value,
                'photo_type' => $rule->photoType()->value,
                'unmatched_files' => array_slice($unmatched, 0, 50),
            ],
            warnings: [],
        );
    }

    /**
     * @return array{0: DocumentRule|null, 1: list<array{filename: string, path: string, parcel_ids: list<int>}>, 2: list<string>}
     */
    private function inspect(string $sourcePath): array
    {
        $dir = $this->extractor->extract($sourcePath, dirname($sourcePath).'/extracted');

        $pdfs = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                $pdfs[] = $file->getPathname();
            }
        }

        $rule = $this->detectRule($pdfs);
        $matched = [];
        $unmatched = [];

        foreach ($pdfs as $path) {
            $filename = basename($path);
            $stem = trim(pathinfo($path, PATHINFO_FILENAME));

            if ($rule === null || ! $rule->matches($stem)) {
                $unmatched[] = $filename;

                continue;
            }

            $parcelIds = $this->parcelIdsFor($rule, $stem);

            if ($parcelIds === []) {
                $unmatched[] = $filename;

                continue;
            }

            $matched[] = ['filename' => $filename, 'path' => $path, 'parcel_ids' => $parcelIds];
        }

        return [$rule, $matched, $unmatched];
    }

    /**
     * Picks the rule matching the most filenames. Deed wins ties — it is the
     * stricter pattern, and DocumentRule::cases() lists it first.
     *
     * @param  list<string>  $pdfs
     */
    private function detectRule(array $pdfs): ?DocumentRule
    {
        $best = null;
        $bestCount = 0;

        foreach (DocumentRule::cases() as $rule) {
            $count = 0;
            foreach ($pdfs as $path) {
                if ($rule->matches(trim(pathinfo($path, PATHINFO_FILENAME)))) {
                    $count++;
                }
            }

            if ($count > $bestCount) {
                $best = $rule;
                $bestCount = $count;
            }
        }

        return $bestCount > 0 ? $best : null;
    }

    /**
     * A deed number can sit on more than one parcel, so this returns every
     * match rather than the first one.
     *
     * @return list<int>
     */
    private function parcelIdsFor(DocumentRule $rule, string $stem): array
    {
        if ($rule === DocumentRule::Deed) {
            return DB::table('deeds')->where('deed_no', $stem)->pluck('parcel_id')->unique()->values()->all();
        }

        preg_match('/^(.+?)\s*-\s*(.+?)$/u', $stem, $m);

        return DB::table('parcels')
            ->join('plans', 'plans.id', '=', 'parcels.plan_id')
            ->where('parcels.parcel_no', trim($m[1]))
            ->where('plans.plan_no', trim($m[2]))
            ->pluck('parcels.id')
            ->all();
    }
}
