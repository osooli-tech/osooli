<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\DatabaseEnum;
use App\Support\Geo\ParcelPlacement;
use App\Support\ParcelGeometry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Works out what an import file would do, without writing anything.
 *
 * Every feature becomes one line in items.jsonl saying, for the deed, its
 * parcel, its owners, boundaries and survey decisions: create, update
 * (with each field's old and new value), leave alone — or why it cannot be
 * imported, or what a person must decide first. The review page shows that,
 * and DeedImportApplier later carries out exactly what it says.
 *
 * Rules that keep a careless file from doing damage:
 *
 *  - Nothing is ever deleted; a record missing from the file is left alone.
 *  - An empty value never erases one the database holds.
 *  - A deed number already on another parcel, a value outside a fixed
 *    list, a malformed number or date, or an invalid polygon is an error.
 *  - An owner who only *might* be someone already recorded, and a district
 *    that does not exist, wait for a person's decision.
 */
final class DeedImportAnalyzer
{
    private const CHUNK = 500;

    public const PARCEL_FIELDS = ['parcel_no', 'asset_type', 'land_transaction', 'allocation_method', 'fall_in', 'm_price', 'parcel_price'];

    public const DEED_FIELDS = ['deed_no', 'deed_date_hijri', 'deed_area', 'deed_status', 'deed_class'];

    public const OWNER_FIELDS = ['name', 'phone', 'email', 'whatsapp'];

    public const BOUNDARY_FIELDS = ['n_border', 'n_dim', 's_border', 's_dim', 'e_border', 'e_dim', 'w_border', 'w_dim', 'measured_area', 'survey_date', 'matches_deed'];

    public const SURVEY_FIELDS = ['qrar_no', 'report_no', 'qrar_source', 'folder'];

    private const NUMERIC = ['deed_area', 'm_price', 'parcel_price', 'n_dim', 's_dim', 'e_dim', 'w_dim', 'measured_area'];

    /** Field => the column whose fixed list of values it must come from. */
    private const ENUMS = [
        'deed_status' => 'deed_status', 'deed_class' => 'deed_class', 'asset_type' => 'asset_type',
        'land_transaction' => 'land_transaction', 'allocation_method' => 'allocation_method',
        'fall_in' => 'fall_in', 'qrar_source' => 'qrar_source',
    ];

    /** Deed area and polygon area further apart than this are flagged. */
    private const AREA_TOLERANCE = 0.20;

    private OwnerMatcher $owners;

    private LocationIndex $locations;

    /** @var array<string, array<string, mixed>> decisions the file needs, by key */
    private array $decisions = [];

    /** @var array<int, int> deed ids seen so far => feature index */
    private array $deedIds = [];

    /**
     * "deed number | parcel GEO ID" seen so far => feature index. One deed
     * number may cover several parcels — a deed row per parcel — so only
     * the same number on the same parcel twice is a duplicate.
     *
     * @var array<string, int>
     */
    private array $deedNumbers = [];

    /** @var array<string, array{index: int, signature: string}> geo_id => first feature carrying it */
    private array $parcelsSeen = [];

    /** @var array<string, array<string, mixed>> owner key => how the first mention resolved */
    private array $ownersSeen = [];

    /** @var array<string, list<string>> */
    private array $enumValues = [];

    /** @var array<string, int> normalised office name => id */
    private array $offices = [];

    /** @var array<int, string> office id => name, for suggestions */
    private array $officeNames = [];

    /** @var array<string, true> every geo_id the file describes */
    private array $geoIdsInFile = [];

    /** @var array<int, string> feature index => parent geo_id not yet found */
    private array $pendingParents = [];

    /** @return array<string, mixed> the finished run */
    public function run(string $id, ?User $user): array
    {
        @set_time_limit(0);
        // Large files: the owner index and per-file bookkeeping grow with them.
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $run = ImportRuns::find($id) ?? ['id' => $id];
        $run = array_merge($run, [
            'state' => 'analysing', 'done' => 0, 'error' => null,
            'counts' => ['total' => 0, 'new' => 0, 'changed' => 0, 'same' => 0, 'decision' => 0, 'error' => 0, 'warnings' => 0],
        ]);
        ImportRuns::save($run);

        $out = fopen(ImportRuns::path($id, 'items.jsonl'), 'wb');

        try {
            if ($out === false) {
                throw new RuntimeException('Cannot write the analysis.');
            }

            $this->owners = new OwnerMatcher;
            $this->locations = new LocationIndex;
            foreach (DB::table('engineering_offices')->get(['id', 'name']) as $office) {
                $this->offices[Normalise::arabic((string) $office->name)] = (int) $office->id;
                $this->officeNames[(int) $office->id] = (string) $office->name;
            }
            foreach (self::ENUMS as $column) {
                $this->enumValues[$column] = DatabaseEnum::for($column);
            }

            $stream = new GeoJsonFeatureStream(ImportRuns::path($id, 'source.geojson'));
            $batch = [];

            foreach ($stream->features() as $index => $feature) {
                if ($index === 0) {
                    $run['header'] = $stream->header()['sokuki'] ?? null;
                }

                $batch[$index] = $feature;

                if (count($batch) === self::CHUNK) {
                    $this->analyseBatch($batch, $out, $run);
                    $batch = [];
                    ImportRuns::save($run);
                }
            }

            if ($batch !== []) {
                $this->analyseBatch($batch, $out, $run);
            }

            if ($run['counts']['total'] === 0) {
                throw new RuntimeException('empty_file');
            }

            $run['decisions_needed'] = $this->decisions;
            // A parent named before it appeared in the file is fine; one that
            // never appears anywhere is not.
            $run['unresolved_parents'] = array_filter(
                $this->pendingParents,
                fn (string $geoId): bool => ! isset($this->geoIdsInFile[$geoId])
            );
            $run['counts']['warnings'] += count($run['unresolved_parents']);
            $run['state'] = 'analysed';
        } catch (Throwable $e) {
            report($e);
            $run['state'] = 'failed';
            $run['error'] = strtok($e->getMessage(), "\n") ?: $e::class;
        } finally {
            if (is_resource($out)) {
                fclose($out);
            }
        }

        $run['analysed_at'] = now()->toIso8601String();
        ImportRuns::save($run);

        return $run;
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     * @param  resource  $out
     * @param  array<string, mixed>  $run
     */
    private function analyseBatch(array $features, $out, array &$run): void
    {
        $records = array_map(ImportRecord::fromFeature(...), $features);
        $existing = $this->prefetch($records);

        foreach ($records as $index => $record) {
            $item = $this->analyse($index, $record, $existing);

            fwrite($out, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n");

            $run['counts']['total']++;
            $run['counts'][$item['status']]++;
            $run['counts']['warnings'] += count($item['warnings']);
            $run['done']++;
        }
    }

    /**
     * Everything the batch's rows might match, in a handful of queries.
     *
     * @param  array<int, ImportRecord>  $records
     * @return array<string, mixed>
     */
    private function prefetch(array $records): array
    {
        $deedIds = array_filter(array_map(static fn (ImportRecord $r) => $r->deed['id'], $records));
        $deedNos = array_filter(array_map(static fn (ImportRecord $r) => $r->deed['deed_no'], $records));
        $geoIds = array_filter(array_map(static fn (ImportRecord $r) => $r->parcel['geo_id'], $records));

        $deedColumns = ['deeds.*', 'parcels.geo_id'];
        $deeds = DB::table('deeds')->join('parcels', 'parcels.id', '=', 'deeds.parcel_id')
            ->where(fn ($q) => $q->whereIn('deeds.id', $deedIds ?: [0])->orWhereIn('deeds.deed_no', $deedNos ?: ['']))
            ->get($deedColumns);

        $parentGeoIds = array_filter(array_map(static fn (ImportRecord $r) => $r->parcel['parent_geo_id'], $records));

        $parcels = DB::table('parcels')->leftJoin('plans', 'plans.id', '=', 'parcels.plan_id')
            ->leftJoin('parcels as parents', 'parents.id', '=', 'parcels.parent_parcel_id')
            ->whereIn('parcels.geo_id', $geoIds ?: [''])
            ->selectRaw('parcels.*, plans.plan_no, parents.geo_id AS parent_geo_id, ST_AsGeoJSON(parcels.geom, 8) AS geojson')
            ->get()->keyBy('geo_id');

        $parcelIds = $parcels->pluck('id')->all() ?: [0];
        $deedIdsFound = $deeds->pluck('id')->all() ?: [0];

        return [
            'parent_ids' => DB::table('parcels')->whereIn('geo_id', $parentGeoIds ?: [''])->pluck('id', 'geo_id'),
            'deeds_by_id' => $deeds->keyBy('id'),
            // A deed number and the parcel together identify a deed.
            'deeds_by_no' => $deeds->keyBy(static fn (object $d): string => $d->deed_no.'|'.$d->geo_id),
            'parcels' => $parcels,
            'boundaries' => DB::table('parcel_boundaries')->whereIn('parcel_id', $parcelIds)->get()->keyBy('parcel_id'),
            'surveys' => DB::table('survey_decisions')->whereIn('parcel_id', $parcelIds)->get()->groupBy('parcel_id'),
            'links' => DB::table('deed_owners')->whereIn('deed_id', $deedIdsFound)->get()->groupBy('deed_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function analyse(int $index, ImportRecord $record, array $existing): array
    {
        $item = [
            'index' => $index,
            'label' => $record->label(),
            'status' => 'same',
            'errors' => [],
            'warnings' => [],
            'decisions' => [],
        ];

        foreach ($record->conflicts as $field) {
            $item['warnings'][] = ['code' => 'flat_nested_differ', 'field' => $field];
        }

        $geoId = $record->parcel['geo_id'];
        if ($geoId === null) {
            $item['errors'][] = ['code' => 'geo_id_missing'];

            return $this->finish($item);
        }

        // ── Parcel ──
        $parcelRow = $existing['parcels'][$geoId] ?? null;
        $parcelValues = $this->values($record->parcel, self::PARCEL_FIELDS, $item);
        $item['parcel'] = ['geo_id' => $geoId, 'id' => $parcelRow?->id, 'action' => $parcelRow ? 'same' : 'create'];

        if (isset($this->parcelsSeen[$geoId])) {
            // A second deed on a parcel already described above: that first
            // description is the one applied.
            $item['parcel']['action'] = 'repeat';
            $item['parcel']['of'] = $this->parcelsSeen[$geoId]['index'];
            if ($this->parcelsSeen[$geoId]['signature'] !== md5(serialize($parcelValues).json_encode($record->geometry))) {
                $item['warnings'][] = ['code' => 'parcel_differs_in_file', 'of' => $this->parcelsSeen[$geoId]['index']];
            }
        } else {
            $this->parcelsSeen[$geoId] = ['index' => $index, 'signature' => md5(serialize($parcelValues).json_encode($record->geometry))];

            if ($parcelRow === null) {
                $item['parcel']['values'] = $parcelValues;
            } else {
                $item['parcel']['changes'] = $this->changes((array) $parcelRow, $parcelValues);
                if ($parcelRow->deleted_at !== null) {
                    $item['warnings'][] = ['code' => 'parcel_archived'];
                }
            }

            $this->geometry($record, $parcelRow, $item);
            $this->plan($record, $parcelRow, $item);
            $this->parent($record, $parcelRow, $existing, $item);

            if (! empty($item['parcel']['changes']) || isset($item['parcel']['geometry'])) {
                $item['parcel']['action'] = $parcelRow ? 'update' : 'create';
            }
        }

        $this->geoIdsInFile[$geoId] = true;

        // ── Deed and owners — unless the feature is a parcel alone ──
        if ($record->hasDeed) {
            $this->deed($record, $existing, $geoId, $item);

            if ($record->owners !== null) {
                $this->owners($record, $existing, $item);
            }
        } else {
            $item['deed'] = ['action' => 'none'];
            if ($record->owners !== null && $record->owners !== []) {
                $item['warnings'][] = ['code' => 'owners_without_deed'];
            }
        }

        // ── Boundaries and survey decisions (with the parcel's first deed) ──
        if ($item['parcel']['action'] !== 'repeat') {
            $parcelId = $parcelRow?->id;
            if ($record->boundary !== null) {
                $values = $this->values($record->boundary, self::BOUNDARY_FIELDS, $item);
                $values['engineering_office_id'] = $this->office($record->engineeringOffice, $item);
                $current = $parcelId ? ($existing['boundaries'][$parcelId] ?? null) : null;
                $changes = $current ? $this->changes((array) $current, $values) : [];
                if ($current === null && array_filter($values, static fn ($v) => $v !== null) !== []) {
                    $item['boundary'] = ['action' => 'create', 'values' => $values];
                } elseif ($changes !== [] || isset($item['office_key'])) {
                    $item['boundary'] = ['action' => 'update', 'id' => $current->id, 'changes' => $changes];
                }
                if (isset($item['boundary']) && isset($item['office_key'])) {
                    $item['boundary']['office_key'] = $item['office_key'];
                }
                unset($item['office_key']);
            }

            if ($record->surveyDecisions !== null) {
                $this->surveys($record, $parcelId ? ($existing['surveys'][$parcelId] ?? collect()) : collect(), $item);
            }
        }

        $this->checks($record, $item);
        $this->placement($record, $parcelRow, $item);

        return $this->finish($item);
    }

    /**
     * The unit's parent (a flat's building), by GEO ID. The parent may be
     * described later in the same file, so an unknown one is only flagged
     * once the whole file has been read.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $item
     */
    private function parent(ImportRecord $record, ?object $parcelRow, array $existing, array &$item): void
    {
        $parent = $record->parcel['parent_geo_id'];
        $current = $parcelRow === null ? null : $parcelRow->parent_geo_id;

        if ($parent === null || $parent === $current) {
            return;
        }

        if ($parent === $record->parcel['geo_id']) {
            $item['errors'][] = ['code' => 'parent_self'];

            return;
        }

        $item['parcel']['parent_geo_id'] = $parent;
        $item['parcel']['changes']['parent_geo_id'] = [$current, $parent];

        if (! isset($existing['parent_ids'][$parent]) && ! isset($this->geoIdsInFile[$parent])) {
            $this->pendingParents[$item['index']] = $parent;
        }
    }

    /**
     * The engineering office a boundary names: its id when known, otherwise
     * a decision — create it, or pick the office it probably means.
     *
     * @param  array<string, mixed>  $item
     */
    private function office(?string $name, array &$item): ?int
    {
        if ($name === null) {
            return null;
        }

        $key = Normalise::arabic($name);
        if (isset($this->offices[$key])) {
            return $this->offices[$key];
        }

        $decision = 'office:'.$key;
        if (! isset($this->decisions[$decision])) {
            $suggestions = [];
            foreach ($this->officeNames as $id => $label) {
                $other = Normalise::arabic($label);
                if (str_contains($other, $key) || str_contains($key, $other) || levenshtein($key, $other) <= 8) {
                    $suggestions[] = ['id' => $id, 'label' => $label];
                }
            }
            $this->decisions[$decision] = ['type' => 'office', 'name' => $name, 'suggestions' => array_slice($suggestions, 0, 5)];
        }

        $item['decisions'][] = $decision;
        $item['office_key'] = $decision;

        return null;
    }

    /** @param  array<string, mixed>  $item */
    private function geometry(ImportRecord $record, ?object $parcelRow, array &$item): void
    {
        if ($record->geometry === null) {
            return;
        }

        try {
            $checked = ParcelGeometry::validate((string) json_encode($record->geometry));
        } catch (InvalidArgumentException $e) {
            $item['errors'][] = ['code' => 'geometry_invalid', 'detail' => $e->getMessage()];

            return;
        }

        $item['area'] = round($checked['area'], 2);

        if ($parcelRow !== null && $parcelRow->geojson !== null
            && self::sameGeometry((string) $parcelRow->geojson, $checked['geojson'])) {
            return;
        }

        $item['parcel']['geometry'] = $checked['geojson'];
        if ($parcelRow !== null) {
            $item['parcel']['geometry_change'] = [
                'old' => $parcelRow->geojson === null ? null : round((float) DB::selectOne(
                    'SELECT '.Spatial::areaSqm('geom').' AS a FROM parcels WHERE id = ?',
                    [$parcelRow->id]
                )?->a, 2),
                'new' => round($checked['area'], 2),
            ];
        }
    }

    /** @param  array<string, mixed>  $item */
    private function plan(ImportRecord $record, ?object $parcelRow, array &$item): void
    {
        $districtId = null;
        $districtKey = null;

        // A plan already on record fixes the location by itself; a city name
        // that cannot be pinned down then does not matter.
        $knownPlan = $record->planNo !== null && $this->locations->plan($record->planNo) !== null;

        if ($record->location['city'] !== null) {
            $city = $this->locations->city($record->location['region'], $record->location['city'], $record->location['district']);

            if ($city['id'] === null) {
                if (! $knownPlan) {
                    $item['errors'][] = ['code' => $city['error'], 'detail' => $record->location['city']];
                }
            } elseif ($record->location['district'] !== null) {
                $districtId = $this->locations->district($city['id'], $record->location['district']);

                if ($districtId === null) {
                    $districtKey = 'district:'.$city['id'].':'.Normalise::arabic($record->location['district']);
                    $this->decisions[$districtKey] ??= [
                        'type' => 'district',
                        'name' => $record->location['district'],
                        'city' => $record->location['city'],
                        'city_id' => $city['id'],
                        'suggestions' => $this->locations->districtSuggestions($city['id'], $record->location['district']),
                    ];
                    $item['decisions'][] = $districtKey;
                }
            }
        }

        if ($record->planNo === null) {
            return;
        }

        $plan = $this->locations->plan($record->planNo);
        $item['plan'] = ['plan_no' => $record->planNo, 'id' => $plan['id'] ?? null, 'action' => $plan ? 'use' : 'create',
            'district_id' => $districtId, 'district_key' => $districtKey];

        if ($plan !== null && ($districtId !== null || $districtKey !== null) && $plan['district_id'] !== $districtId) {
            // A plan belongs to one district; the file cannot move it.
            $item['warnings'][] = ['code' => 'plan_in_other_district', 'detail' => $record->planNo];
        }

        if ($parcelRow !== null && trim((string) $parcelRow->plan_no) !== $record->planNo) {
            $item['parcel']['changes']['plan_no'] = [$parcelRow->plan_no, $record->planNo];
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $item
     */
    private function deed(ImportRecord $record, array $existing, string $geoId, array &$item): void
    {
        $deedNo = $record->deed['deed_no'];
        $values = $this->values($record->deed, self::DEED_FIELDS, $item);

        if ($deedNo !== null && $record->deed['id'] === null) {
            $key = $deedNo.'|'.$geoId;
            if (isset($this->deedNumbers[$key])) {
                $item['errors'][] = ['code' => 'deed_duplicate_in_file', 'of' => $this->deedNumbers[$key]];
            }
            $this->deedNumbers[$key] ??= $item['index'];
        }

        $row = null;
        if ($record->deed['id'] !== null) {
            $row = $existing['deeds_by_id'][$record->deed['id']] ?? null;
        }
        if ($row === null && $deedNo !== null) {
            $row = $existing['deeds_by_no'][$deedNo.'|'.$geoId] ?? null;
        }

        if ($record->deed['id'] !== null) {
            if (isset($this->deedIds[$record->deed['id']])) {
                // A row copied in a GIS program keeps its original's deed_id.
                $item['errors'][] = ['code' => 'deed_id_duplicate_in_file', 'of' => $this->deedIds[$record->deed['id']]];
            }
            $this->deedIds[$record->deed['id']] ??= $item['index'];
        }

        // Only an id can point at a deed recorded on another parcel; a number
        // is matched on this parcel alone.
        if ($row !== null && $row->geo_id !== $geoId) {
            $item['errors'][] = ['code' => 'deed_on_other_parcel', 'detail' => $row->geo_id];
        }

        // Matched by id but carrying another number: a copied row meant as
        // a new deed, or a renumbering. Neither is guessed at.
        if ($row !== null && $record->deed['id'] !== null && (int) $row->id === $record->deed['id']
            && $deedNo !== null && $row->deed_no !== null && (string) $row->deed_no !== $deedNo) {
            $item['errors'][] = ['code' => 'deed_id_number_mismatch', 'detail' => (string) $row->deed_no];
        }

        if ($row === null) {
            $item['deed'] = ['action' => 'create', 'values' => $values];
            if ($deedNo === null) {
                $item['warnings'][] = ['code' => 'deed_without_number'];
            }

            return;
        }

        $changes = $this->changes((array) $row, $values);
        $item['deed'] = ['action' => $changes === [] ? 'same' : 'update', 'id' => (int) $row->id, 'changes' => $changes];

        if ($row->deleted_at !== null) {
            $item['warnings'][] = ['code' => 'deed_archived'];
        }
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $item
     */
    private function owners(ImportRecord $record, array $existing, array &$item): void
    {
        $deedId = $item['deed']['id'] ?? null;
        $links = $deedId ? ($existing['links'][$deedId] ?? collect())->keyBy('owner_id') : collect();
        $item['owners'] = [];
        $linkedHere = [];
        $shares = [];

        foreach ($record->owners as $owner) {
            if ($owner['name'] === null && $owner['national_id'] === null) {
                $item['errors'][] = ['code' => 'owner_without_name'];

                continue;
            }

            $key = OwnerMatcher::key($owner);
            $share = $owner['ownership_share'];
            if ($share !== null && ! is_numeric($share)) {
                $item['errors'][] = ['code' => 'not_a_number', 'field' => 'ownership_share', 'detail' => $share];
                $share = null;
            }
            $share = $share === null ? null : round((float) $share, 2);
            if ($share !== null) {
                $shares[] = $share;
            }

            $resolution = $this->ownersSeen[$key] ??= $this->resolveOwner($owner, $key);
            $entry = ['key' => $key, 'name' => $owner['name'], 'national_id' => $owner['national_id'], 'share' => $share] + $resolution;

            if ($resolution['action'] === 'decide') {
                $item['decisions'][] = 'owner:'.$key;
            }
            if (! empty($resolution['archived'])) {
                $item['warnings'][] = ['code' => 'owner_archived', 'detail' => (string) $owner['name']];
            }

            $ownerId = $resolution['id'] ?? null;
            if ($ownerId !== null && $links->has($ownerId)) {
                $linkedHere[] = $ownerId;
                $current = $links[$ownerId]->ownership_share;
                $entry['link'] = $share !== null && ($current === null || round((float) $current, 2) !== $share)
                    ? ['action' => 'update', 'old' => $current === null ? null : (float) $current]
                    : ['action' => 'same'];
            } else {
                $entry['link'] = ['action' => 'create'];
            }

            $item['owners'][] = $entry;
        }

        // Owners recorded on the deed but absent from the file stay linked.
        foreach ($links as $ownerId => $link) {
            if (! in_array($ownerId, $linkedHere, true)) {
                $row = $this->owners->row((int) $ownerId);
                $item['warnings'][] = ['code' => 'owner_not_in_file', 'detail' => $row['name'] ?? (string) $ownerId];
            }
        }

        if ($shares !== [] && count($shares) === count($record->owners) && abs(array_sum($shares) - 100) > 0.5) {
            $item['warnings'][] = ['code' => 'shares_not_100', 'detail' => (string) array_sum($shares)];
        }
    }

    /**
     * @param  array<string, mixed>  $owner
     * @return array<string, mixed>
     */
    private function resolveOwner(array $owner, string $key): array
    {
        $values = array_intersect_key($owner, array_flip(['name', 'national_id', 'phone', 'email', 'whatsapp']));
        $found = $this->owners->match($owner);

        if ($found['match'] !== null) {
            $row = $this->owners->row($found['match']);
            $changes = $this->changes($row ?? [], array_intersect_key($values, array_flip(self::OWNER_FIELDS)));

            return ['action' => $changes === [] ? 'link' : 'update', 'id' => $found['match'], 'changes' => $changes,
                'archived' => ($row['deleted_at'] ?? null) !== null];
        }

        if ($found['candidates'] !== []) {
            $this->decisions['owner:'.$key] ??= [
                'type' => 'owner',
                'owner' => $values,
                'candidates' => $found['candidates'],
            ];

            return ['action' => 'decide', 'values' => $values];
        }

        return ['action' => 'create', 'values' => $values];
    }

    /**
     * @param  Collection<int, object>  $current
     * @param  array<string, mixed>  $item
     */
    private function surveys(ImportRecord $record, $current, array &$item): void
    {
        foreach ($record->surveyDecisions as $decision) {
            $values = $this->values($decision, self::SURVEY_FIELDS, $item);
            $row = $current->firstWhere('id', $decision['id'])
                ?? ($decision['qrar_no'] !== null ? $current->firstWhere('qrar_no', $decision['qrar_no']) : null);

            if ($row === null) {
                if (array_filter($values, static fn ($v) => $v !== null) !== []) {
                    $item['survey'][] = ['action' => 'create', 'values' => $values];
                }

                continue;
            }

            $changes = $this->changes((array) $row, $values);
            if ($changes !== []) {
                $item['survey'][] = ['action' => 'update', 'id' => (int) $row->id, 'changes' => $changes];
            }
        }
    }

    /**
     * Whether the parcel lies in the district its plan is in — advice only,
     * like everywhere else: boundaries can be out of date or approximate.
     *
     * @param  array<string, mixed>  $item
     */
    private function placement(ImportRecord $record, ?object $parcelRow, array &$item): void
    {
        if (($item['parcel']['action'] ?? null) === 'repeat' || $item['errors'] !== []) {
            return;
        }

        $geojson = $item['parcel']['geometry'] ?? $parcelRow?->geojson;
        if ($geojson === null) {
            return;
        }

        $districtId = $item['plan']['district_id'] ?? null;
        if ($districtId === null) {
            $planNo = $record->planNo ?? ($parcelRow?->plan_no === null ? null : (string) $parcelRow->plan_no);
            $districtId = $planNo === null ? null : ($this->locations->plan($planNo)['district_id'] ?? null);
        }

        $message = ParcelPlacement::message(ParcelPlacement::forGeometry((string) $geojson, $districtId));
        if ($message !== null) {
            $item['warnings'][] = ['code' => 'placement', 'detail' => $message];
        }
    }

    /** @param  array<string, mixed>  $item */
    private function checks(ImportRecord $record, array &$item): void
    {
        $deedArea = $record->deed['deed_area'];

        if (isset($item['area']) && is_numeric($deedArea) && (float) $deedArea > 0
            && abs($item['area'] - (float) $deedArea) / (float) $deedArea > self::AREA_TOLERANCE) {
            $item['warnings'][] = ['code' => 'area_mismatch', 'detail' => number_format((float) $deedArea).' / '.number_format($item['area'])];
        }
    }

    /**
     * The values of `$fields`, checked: numbers must be numbers, fixed-list
     * fields must hold a listed value, the Hijri date must be YYYY-MM-DD.
     * Anything wrong is recorded as an error and left out.
     *
     * @param  array<string, mixed>  $source
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function values(array $source, array $fields, array &$item): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $source[$field] ?? null;

            if ($value === null || $value === '') {
                $values[$field] = null;

                continue;
            }

            if (in_array($field, self::NUMERIC, true)) {
                if (! is_numeric($value)) {
                    $item['errors'][] = ['code' => 'not_a_number', 'field' => $field, 'detail' => (string) $value];
                    $values[$field] = null;

                    continue;
                }
                $value = round((float) $value, 2);
            }

            if (isset(self::ENUMS[$field]) && ! in_array($value, $this->enumValues[self::ENUMS[$field]], true)) {
                $item['errors'][] = ['code' => 'not_in_list', 'field' => $field, 'detail' => (string) $value];
                $values[$field] = null;

                continue;
            }

            if ($field === 'deed_date_hijri' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $item['errors'][] = ['code' => 'bad_date', 'field' => $field, 'detail' => (string) $value];
                $values[$field] = null;

                continue;
            }

            $values[$field] = is_float($value) || is_bool($value) ? $value : trim((string) $value);
        }

        return $values;
    }

    /**
     * Fields whose incoming value differs from the stored one. An empty
     * incoming value is not a change: the file never erases data.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changes(array $current, array $incoming): array
    {
        $changes = [];

        foreach ($incoming as $field => $new) {
            if ($new === null) {
                continue;
            }

            $old = $current[$field] ?? null;
            $same = is_bool($new)
                ? $old !== null && (bool) $old === $new
                : (is_float($new)
                ? $old !== null && is_numeric($old) && round((float) $old, 2) === $new
                : (string) $old === (string) $new);

            if (! $same) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    /** Same polygon, give or take the last decimal places. */
    private static function sameGeometry(string $a, string $b): bool
    {
        $round = static function (mixed $value) use (&$round): mixed {
            return is_array($value) ? array_map($round, $value) : (is_float($value) || is_int($value) ? round((float) $value, 7) : $value);
        };

        $first = json_decode($a, true);
        $second = json_decode($b, true);

        return is_array($first) && is_array($second)
            && $round($first['coordinates'] ?? null) == $round($second['coordinates'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function finish(array $item): array
    {
        $item['decisions'] = array_values(array_unique($item['decisions']));

        $touches = ! in_array($item['deed']['action'] ?? 'same', ['same', 'none'], true)
            || in_array($item['parcel']['action'] ?? 'same', ['create', 'update'], true)
            || isset($item['boundary']) || ! empty($item['survey'])
            || ! empty(array_filter($item['owners'] ?? [], static fn (array $o): bool => $o['action'] !== 'link' || $o['link']['action'] !== 'same'))
            || ($item['plan']['action'] ?? null) === 'create';

        $item['status'] = match (true) {
            $item['errors'] !== [] => 'error',
            $item['decisions'] !== [] => 'decision',
            ($item['deed']['action'] ?? null) === 'create' => 'new',
            ($item['deed']['action'] ?? null) === 'none' && ($item['parcel']['action'] ?? null) === 'create' => 'new',
            $touches => 'changed',
            default => 'same',
        };

        return $item;
    }
}
