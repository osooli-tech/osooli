<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ArchivedValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Archived field values in words a person reads: which record ("the deed
 * 410100000001 of parcel BUTAYN-05"), which field ("deed date"), what it
 * was and what it is now — an id shown as the plan, district or city it
 * points at — and a link to the parcel it belongs to.
 *
 * The rows arrive as a page of ArchivedValue; everything they point at is
 * fetched in one query per table, not one per row.
 */
final class ArchivedValues
{
    /**
     * Kinds of correction, for the filter: each a set of table.field pairs.
     *
     * @var array<string, list<string>>
     */
    public const CATEGORIES = [
        'phone' => ['owners.phone'],
        'national_id' => ['owners.national_id'],
        'owner_name' => ['owners.name'],
        'borders' => ['parcel_boundaries.n_border', 'parcel_boundaries.s_border', 'parcel_boundaries.e_border', 'parcel_boundaries.w_border'],
        'deed' => ['deeds.deed_date_hijri', 'deeds.deed_no'],
        'plan' => ['parcels.plan_id'],
        'location' => ['plans.district_id', 'districts.city_id', 'districts.name_en'],
    ];

    /** @param  Builder<ArchivedValue>  $query */
    public static function scope(Builder $query, string $category): void
    {
        $pairs = self::CATEGORIES[$category] ?? null;
        if ($pairs === null) {
            return;
        }

        $query->where(function (Builder $q) use ($pairs): void {
            foreach ($pairs as $pair) {
                [$table, $field] = explode('.', $pair);
                $q->orWhere(fn (Builder $x) => $x->where('record_table', $table)->where('field', $field));
            }
        });
    }

    /**
     * @param  Collection<int, ArchivedValue>  $rows
     * @return array<int, array{record: string, field: string, before: string, now: string, url: string|null}> by row id
     */
    public static function describe(Collection $rows): array
    {
        $ids = static fn (string $table): array => $rows->where('record_table', $table)->pluck('record_id')->unique()->values()->all();

        $records = [
            'owners' => DB::table('owners')->whereIn('id', $ids('owners'))->get(['id', 'name', 'national_id', 'phone'])->keyBy('id'),
            'deeds' => DB::table('deeds as d')->leftJoin('parcels as p', 'p.id', '=', 'd.parcel_id')
                ->whereIn('d.id', $ids('deeds'))->get(['d.id', 'd.deed_no', 'd.deed_date_hijri', 'd.parcel_id', 'p.geo_id'])->keyBy('id'),
            'parcel_boundaries' => DB::table('parcel_boundaries as b')->leftJoin('parcels as p', 'p.id', '=', 'b.parcel_id')
                ->whereIn('b.id', $ids('parcel_boundaries'))->get(['b.id', 'b.parcel_id', 'b.n_border', 'b.s_border', 'b.e_border', 'b.w_border', 'p.geo_id'])->keyBy('id'),
            'parcels' => DB::table('parcels')->whereIn('id', $ids('parcels'))->get(['id', 'id as parcel_id', 'geo_id', 'plan_id'])->keyBy('id'),
            'plans' => DB::table('plans')->whereIn('id', $ids('plans'))->get(['id', 'plan_no', 'district_id'])->keyBy('id'),
            'districts' => DB::table('districts')->whereIn('id', $ids('districts'))->get(['id', 'name_ar', 'name_en', 'city_id'])->keyBy('id'),
        ];

        // Values that are ids of other rows, shown by name: plan, district, city.
        $refs = ['parcels.plan_id' => 'plans', 'plans.district_id' => 'districts', 'districts.city_id' => 'cities'];
        $wanted = ['plans' => [], 'districts' => [], 'cities' => []];
        foreach ($rows as $row) {
            $target = $refs[$row->record_table.'.'.$row->field] ?? null;
            if ($target !== null) {
                $current = $records[$row->record_table][$row->record_id] ?? null;
                foreach ([$row->value, $current->{$row->field} ?? null] as $id) {
                    if (is_numeric($id)) {
                        $wanted[$target][] = (int) $id;
                    }
                }
            }
        }
        $names = [
            'plans' => DB::table('plans')->whereIn('id', $wanted['plans'])->pluck('plan_no', 'id'),
            'districts' => DB::table('districts')->whereIn('id', $wanted['districts'])->pluck('name_ar', 'id'),
            'cities' => DB::table('cities')->whereIn('id', $wanted['cities'])->pluck('name_ar', 'id'),
        ];

        $shown = static function (mixed $value, ?string $target) use ($names): string {
            if ($value === null || $value === '') {
                return __('archive.value_empty');
            }
            if ($target !== null) {
                return (string) ($names[$target][(int) $value] ?? __('archive.value_missing', ['id' => $value]));
            }

            return (string) $value;
        };

        $described = [];
        foreach ($rows as $row) {
            $record = $records[$row->record_table][$row->record_id] ?? null;
            $target = $refs[$row->record_table.'.'.$row->field] ?? null;

            $described[$row->id] = [
                'record' => self::recordLabel($row->record_table, $record, $row->record_id),
                'field' => __('archive.fields.'.$row->record_table.'.'.$row->field),
                'before' => $shown($row->value, $target),
                'now' => $record === null ? __('archive.record_gone') : $shown($record->{$row->field} ?? null, $target),
                'url' => isset($record->parcel_id) ? route('parcels.show', $record->parcel_id) : null,
            ];
        }

        return $described;
    }

    private static function recordLabel(string $table, ?object $record, int $id): string
    {
        if ($record === null) {
            return __('archive.tables.'.$table).' #'.$id;
        }

        return match ($table) {
            'owners' => __('archive.labels.owner', ['name' => $record->name, 'id' => $record->national_id ?? '—']),
            'deeds' => __('archive.labels.deed', ['deed' => $record->deed_no ?? __('archive.no_number'), 'parcel' => $record->geo_id ?? '—']),
            'parcel_boundaries' => __('archive.labels.boundary', ['parcel' => $record->geo_id ?? '—']),
            'parcels' => __('archive.labels.parcel', ['parcel' => $record->geo_id]),
            'plans' => __('archive.labels.plan', ['plan' => $record->plan_no]),
            'districts' => __('archive.labels.district', ['name' => $record->name_ar]),
            default => $table.' #'.$id,
        };
    }
}
