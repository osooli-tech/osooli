<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Owner;
use App\Models\User;
use App\Support\ParcelGeometry;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Carries out an analysed import, exactly as the review page showed it,
 * with the decisions and exclusions made there.
 *
 * Everything happens in one transaction: either the whole import lands or
 * none of it does. As it writes it keeps an undo log — every row it created,
 * and for every row it changed the values it replaced and the timestamp it
 * left — which DeedImportUndo uses to put things back.
 */
final class DeedImportApplier
{
    /** Tables in the order their created rows must be deleted on undo. */
    public const CREATED_ORDER = ['deed_owners', 'survey_decisions', 'parcel_boundaries', 'deeds', 'parcels', 'owners', 'plans', 'districts', 'engineering_offices'];

    private string $now;

    /** @var array<string, mixed> */
    private array $undo = [];

    /** @var array<string, array<string, mixed>> the decisions the analysis asked for */
    private array $decisionsNeeded = [];

    /** @var array<string, int> */
    private array $districts = [];

    /** @var array<string, int> */
    private array $plans = [];

    /** @var array<string, int> geo_id => parcel id */
    private array $parcels = [];

    /** @var array<string, int> owner key => owner id */
    private array $owners = [];

    /** @var array<string, int> office decision key => office id */
    private array $offices = [];

    /** @var array<int, string> parcel id => the parent GEO ID to link once every parcel exists */
    private array $parentLinks = [];

    /** @var array<string, int> */
    private array $stats = ['deeds_created' => 0, 'deeds_updated' => 0, 'parcels_created' => 0, 'parcels_updated' => 0,
        'owners_created' => 0, 'owners_updated' => 0, 'links' => 0, 'skipped' => 0];

    /** @return array<string, mixed> the finished run */
    public function run(string $id, ?User $user): array
    {
        @set_time_limit(0);
        // Large files: the owner index and per-file bookkeeping grow with them.
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $run = ImportRuns::find($id) ?? throw new RuntimeException('Import not found.');
        $run['state'] = 'applying';
        $run['done'] = 0;
        ImportRuns::save($run);

        $choices = (array) ($run['choices'] ?? []);
        $excluded = array_flip(array_map('intval', (array) ($run['excluded'] ?? [])));
        $this->decisionsNeeded = (array) ($run['decisions_needed'] ?? []);
        $this->now = now()->format('Y-m-d H:i:s');
        $this->undo = ['created' => array_fill_keys(self::CREATED_ORDER, []), 'updated' => [], 'geometry' => [], 'stamp' => $this->now];

        try {
            DB::transaction(function () use ($id, $choices, $excluded, &$run): void {
                foreach (ImportRuns::items($id) as $index => $item) {
                    $undecided = array_filter($item['decisions'], static fn (string $key): bool => ! isset($choices[$key]));

                    if ($item['status'] === 'error' || $item['status'] === 'same' || isset($excluded[$index]) || $undecided !== []) {
                        $this->stats['skipped'] += $item['status'] === 'same' ? 0 : 1;
                    } else {
                        $this->apply($item, $choices);
                    }

                    if (++$run['done'] % 200 === 0) {
                        ImportRuns::save($run);
                    }
                }

                $this->linkParents($run);
            });

            ImportRuns::writeJson(ImportRuns::path($id, 'undo.json'), $this->undo);
            $run['state'] = 'applied';
            $run['result'] = $this->stats;
        } catch (Throwable $e) {
            report($e);
            $run['state'] = 'apply_failed';
            $run['error'] = strtok($e->getMessage(), "\n") ?: $e::class;
        }

        $run['applied_at'] = now()->toIso8601String();
        $run['applied_by'] = $user?->name;
        ImportRuns::save($run);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $choices
     */
    private function apply(array $item, array $choices): void
    {
        $planId = $this->plan($item['plan'] ?? null, $choices);
        $parcelId = $this->parcel($item, $planId);

        $deed = $item['deed'];
        if ($deed['action'] === 'none') {
            // A parcel alone: nothing about a deed or its owners to write.
            $this->boundaryAndSurvey($item, $parcelId, $choices);

            return;
        }

        if ($deed['action'] === 'create') {
            $deedId = $this->insert('deeds', $deed['values'] + ['parcel_id' => $parcelId]);
            $this->stats['deeds_created']++;
        } else {
            $deedId = (int) $deed['id'];
            if ($deed['action'] === 'update') {
                $this->update('deeds', $deedId, $deed['changes']);
                $this->stats['deeds_updated']++;
            }
        }

        foreach ($item['owners'] ?? [] as $owner) {
            $ownerId = $this->owner($owner, $choices);

            if ($owner['link']['action'] === 'create') {
                $exists = DB::table('deed_owners')->where('deed_id', $deedId)->where('owner_id', $ownerId)->first();
                if ($exists === null) {
                    $this->insert('deed_owners', ['deed_id' => $deedId, 'owner_id' => $ownerId, 'ownership_share' => $owner['share']]);
                    $this->stats['links']++;
                }
            } elseif ($owner['link']['action'] === 'update') {
                $link = DB::table('deed_owners')->where('deed_id', $deedId)->where('owner_id', $ownerId)->first();
                if ($link !== null) {
                    $this->update('deed_owners', (int) $link->id, ['ownership_share' => [$link->ownership_share, $owner['share']]]);
                }
            }
        }

        $this->boundaryAndSurvey($item, $parcelId, $choices);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $choices
     */
    private function boundaryAndSurvey(array $item, int $parcelId, array $choices): void
    {
        if (isset($item['boundary'])) {
            $boundary = $item['boundary'];

            if (isset($boundary['office_key'])) {
                $officeId = $this->office($boundary['office_key'], $choices);
                if ($boundary['action'] === 'create') {
                    $boundary['values']['engineering_office_id'] = $officeId;
                } else {
                    $boundary['changes']['engineering_office_id'] = [null, $officeId];
                }
            }

            $boundary['action'] === 'create'
                ? $this->insert('parcel_boundaries', $boundary['values'] + ['parcel_id' => $parcelId])
                : $this->update('parcel_boundaries', (int) $boundary['id'], $boundary['changes']);
        }

        foreach ($item['survey'] ?? [] as $decision) {
            $decision['action'] === 'create'
                ? $this->insert('survey_decisions', $decision['values'] + ['parcel_id' => $parcelId])
                : $this->update('survey_decisions', (int) $decision['id'], $decision['changes']);
        }
    }

    /**
     * @param  array<string, mixed>|null  $plan
     * @param  array<string, mixed>  $choices
     */
    private function plan(?array $plan, array $choices): ?int
    {
        if ($plan === null) {
            return null;
        }

        if ($plan['action'] === 'use') {
            return (int) $plan['id'];
        }

        if (isset($this->plans[$plan['plan_no']])) {
            return $this->plans[$plan['plan_no']];
        }

        $districtId = $plan['district_id'];
        if ($districtId === null && $plan['district_key'] !== null) {
            $districtId = $this->district($plan['district_key'], $choices);
        }

        return $this->plans[$plan['plan_no']] = $this->insert('plans', ['plan_no' => $plan['plan_no'], 'district_id' => $districtId]);
    }

    /** @param  array<string, mixed>  $choices */
    private function office(string $key, array $choices): int
    {
        $choice = $choices[$key];

        if ($choice !== 'create') {
            return (int) $choice;
        }

        return $this->offices[$key] ??= $this->insert('engineering_offices', ['name' => (string) $this->decisionsNeeded[$key]['name']]);
    }

    /**
     * Point units at their parents, now that every parcel in the file exists.
     *
     * @param  array<string, mixed>  $run
     */
    private function linkParents(array &$run): void
    {
        $missing = [];

        foreach ($this->parentLinks as $parcelId => $parentGeoId) {
            $parentId = DB::table('parcels')->where('geo_id', $parentGeoId)->value('id');

            if ($parentId === null) {
                $missing[] = $parentGeoId;

                continue;
            }

            $this->update('parcels', $parcelId, ['parent_parcel_id' => [null, (int) $parentId]]);
        }

        $run['parents_not_found'] = array_values(array_unique($missing));
    }

    /** @param  array<string, mixed>  $choices */
    private function district(string $key, array $choices): int
    {
        $choice = $choices[$key];

        if ($choice !== 'create') {
            return (int) $choice;
        }

        if (isset($this->districts[$key])) {
            return $this->districts[$key];
        }

        $decision = $this->decisionsNeeded[$key];

        return $this->districts[$key] = $this->insert('districts', [
            'city_id' => (int) $decision['city_id'],
            'name_ar' => (string) $decision['name'],
        ]);
    }

    /** @param  array<string, mixed>  $item */
    private function parcel(array $item, ?int $planId): int
    {
        $parcel = $item['parcel'];
        $geoId = $parcel['geo_id'];

        if ($parcel['action'] === 'repeat' || isset($this->parcels[$geoId])) {
            return $this->parcels[$geoId] ?? (int) $parcel['id'];
        }

        if ($parcel['action'] === 'create') {
            $id = $this->insert('parcels', array_filter($parcel['values'] ?? [], static fn ($v) => $v !== null)
                + ['geo_id' => $geoId, 'plan_id' => $planId]);
            $this->stats['parcels_created']++;
        } else {
            $id = (int) $parcel['id'];
            $changes = $parcel['changes'] ?? [];

            unset($changes['parent_geo_id']);

            if (isset($changes['plan_no'])) {
                $changes['plan_id'] = [DB::table('parcels')->where('id', $id)->value('plan_id'), $planId];
                unset($changes['plan_no']);
            }

            if ($changes !== [] || isset($parcel['geometry'])) {
                $this->update('parcels', $id, $changes);
                $this->stats['parcels_updated']++;
            }
        }

        if (isset($parcel['parent_geo_id'])) {
            $this->parentLinks[$id] = $parcel['parent_geo_id'];
        }

        if (isset($parcel['geometry'])) {
            if ($parcel['action'] !== 'create') {
                $old = DB::selectOne('SELECT ST_AsGeoJSON(geom, 15) AS g FROM parcels WHERE id = ?', [$id]);
                $this->undo['geometry'][$id] = $old?->g;
            }

            ParcelGeometry::replace($id, (string) $parcel['geometry'], 'import');
            // replace() stamps updated_at with the database clock; undo
            // compares against this run's stamp.
            DB::table('parcels')->where('id', $id)->update(['updated_at' => $this->now]);
        }

        return $this->parcels[$geoId] = $id;
    }

    /**
     * @param  array<string, mixed>  $owner
     * @param  array<string, mixed>  $choices
     */
    private function owner(array $owner, array $choices): int
    {
        $key = $owner['key'];

        if (isset($this->owners[$key])) {
            return $this->owners[$key];
        }

        $action = $owner['action'];
        if ($action === 'decide') {
            $choice = $choices['owner:'.$key];
            $action = $choice === 'new' ? 'create' : 'chosen';
            $owner['id'] = $choice === 'new' ? null : (int) $choice;
        }

        if ($action === 'create') {
            $values = $owner['values'];
            $values['phone_normalized'] = $values['phone'] === null ? null : (Owner::normalisePhone((string) $values['phone']) ?: null);
            $this->stats['owners_created']++;

            return $this->owners[$key] = $this->insert('owners', $values);
        }

        $id = (int) $owner['id'];

        if ($action === 'update' && ($owner['changes'] ?? []) !== []) {
            $changes = $owner['changes'];
            if (isset($changes['phone'])) {
                $changes['phone_normalized'] = [null, Owner::normalisePhone((string) $changes['phone'][1]) ?: null];
            }
            $this->update('owners', $id, $changes);
            $this->stats['owners_updated']++;
        }

        return $this->owners[$key] = $id;
    }

    /** @param  array<string, mixed>  $values */
    private function insert(string $table, array $values): int
    {
        $id = (int) DB::table($table)->insertGetId($values + ['created_at' => $this->now, 'updated_at' => $this->now]);
        $this->undo['created'][$table][] = $id;

        return $id;
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $changes */
    private function update(string $table, int $id, array $changes): void
    {
        $current = (array) DB::table($table)->where('id', $id)->first();
        $new = [];
        $old = [];

        foreach ($changes as $field => [, $value]) {
            $new[$field] = $value;
            $old[$field] = $current[$field] ?? null;
        }

        $old['updated_at'] = $current['updated_at'] ?? null;
        DB::table($table)->where('id', $id)->update($new + ['updated_at' => $this->now]);
        $this->undo['updated'][] = ['table' => $table, 'id' => $id, 'old' => $old];
    }
}
