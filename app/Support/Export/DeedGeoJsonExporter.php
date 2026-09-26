<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Models\City;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelBoundary;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\Region;
use App\Models\SurveyDecision;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\DatabaseEnum;
use App\Support\Import\DeedImportAnalyzer;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes the deeds matching an export's filters to a GeoJSON file — and,
 * after them, the matching parcels that have no deed at all, so every
 * parcel on record can leave in the file.
 *
 * One feature per deed (or per deedless parcel), carrying the parcel's
 * polygon, so the file opens as a map layer in QGIS or ArcGIS.
 *
 * Every piece of information has a column of its own — no nested records —
 * so the attribute table can be read and filled in cell by cell. Lists are
 * numbered columns: `owner_1_name`, `owner_1_national_id` … `owner_2_name`,
 * and likewise `survey_1_…` and `document_1_…`. A file carries as many
 * numbered sets as its fullest record needs, and every feature carries the
 * same columns, so the table stays rectangular.
 *
 * History — when a record was created, changed, archived and by whom, a
 * document's size and review — has columns too (`deed_created_at` …). The
 * importer reads past them.
 *
 * Records are read in chunks and each feature is written as it is built, so
 * memory stays flat however many thousands match.
 */
final class DeedGeoJsonExporter
{
    public const FORMAT = 'sokuki-deeds';

    /** 2: one column per value. 1 (still imported) nested owners, boundary… */
    public const VERSION = 2;

    /** Optional parts of each feature; the deed itself is always included. */
    public const GROUPS = ['geometry', 'parcel', 'owners', 'boundary', 'survey', 'documents'];

    private const CHUNK = 500;

    /** Coordinate decimals kept — about a millimetre. */
    private const PRECISION = 8;

    /**
     * How many numbered owner, survey decision and document columns each
     * feature carries: as many as the fullest record in the file needs.
     *
     * @var array{owners: int, survey: int, documents: int}
     */
    private array $slots = ['owners' => 1, 'survey' => 1, 'documents' => 1];

    /** Whether the history columns are written; the sample file leaves them out. */
    private bool $history = true;

    /**
     * Run export `$id` to completion, recording progress in ExportRuns.
     *
     * @param  list<string>  $groups
     * @return array<string, mixed> the finished run
     */
    public function run(string $id, DeedExportFilters $filters, array $groups, ?User $user): array
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $groups = array_values(array_intersect(self::GROUPS, $groups));
        $archived = $filters->includeArchived();
        $run = [
            'id' => $id,
            'state' => 'running',
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'filters' => $filters->active(),
            'groups' => $groups,
            'total' => 0,
            'done' => 0,
            'deedless' => 0,
            'bytes' => 0,
            'error' => null,
        ];
        ExportRuns::save($run);

        $path = ExportRuns::filePath($id);
        $partial = $path.'.part';

        try {
            $deeds = $filters->query($user);
            $parcels = $filters->parcelsWithoutDeeds($user);
            $run['total'] = (clone $deeds)->count() + ($parcels === null ? 0 : (clone $parcels)->count());
            ExportRuns::save($run);

            $this->slots = $this->slotsFor($deeds, $parcels);
            $this->history = true;

            $handle = fopen($partial, 'wb');
            if ($handle === false) {
                throw new RuntimeException('Cannot write the export file.');
            }

            fwrite($handle, '{"type":"FeatureCollection","name":"'.self::FORMAT.'","sokuki":'.self::json([
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'exported_at' => now()->toIso8601String(),
                'exported_by' => $user?->name,
                'filters' => $filters->active(),
                'groups' => $groups,
                'count' => $run['total'],
                'crs' => 'EPSG:4326',
                'layout' => 'flat',
                'slots' => $this->slots,
            ]).',"features":[');

            $first = true;
            $write = function (array $feature) use ($handle, &$first): void {
                fwrite($handle, ($first ? '' : ',')."\n".self::json($feature));
                $first = false;
            };

            $deeds->with($this->relations($groups, $archived, 'parcel.'))
                ->with(array_filter(['parcel' => fn ($q) => $archived ? $q->withTrashed() : $q, 'archivedBy',
                    'owners' => in_array('owners', $groups, true) ? fn ($q) => $archived ? $q->withTrashed() : $q : null]))
                ->chunkById(self::CHUNK, function (Collection $chunk) use ($groups, $write, &$run): void {
                    $geometries = in_array('geometry', $groups, true) ? $this->geometries($chunk->pluck('parcel_id')->all()) : [];

                    foreach ($chunk as $deed) {
                        /** @var Deed $deed */
                        $write($this->feature($deed, $deed->parcel, $groups, $geometries));
                    }

                    $run['done'] += $chunk->count();
                    ExportRuns::save($run);
                });

            $parcels?->with($this->relations($groups, $archived, ''))
                ->chunkById(self::CHUNK, function (Collection $chunk) use ($groups, $write, &$run): void {
                    $geometries = in_array('geometry', $groups, true) ? $this->geometries($chunk->pluck('id')->all()) : [];

                    foreach ($chunk as $parcel) {
                        /** @var Parcel $parcel */
                        $write($this->feature(null, $parcel, $groups, $geometries));
                    }

                    $run['done'] += $chunk->count();
                    $run['deedless'] += $chunk->count();
                    ExportRuns::save($run);
                });

            fwrite($handle, "\n]}\n");
            fclose($handle);
            rename($partial, $path);

            $run['state'] = 'succeeded';
            $run['bytes'] = filesize($path) ?: 0;
        } catch (Throwable $e) {
            report($e);
            @unlink($partial);

            $run['state'] = 'failed';
            $run['error'] = strtok($e->getMessage(), "\n") ?: $e::class;
        }

        $run['finished_at'] = now()->toIso8601String();
        ExportRuns::save($run);

        return $run;
    }

    /**
     * A file in the export's own layout holding a single made-up record: one
     * deed on one parcel, owned by one person, with one survey decision and
     * one document, so every column shows what goes in it. Three owner and
     * two survey and document sets are there to show how more are added.
     * The values are replaced with real ones and the file uploaded like any
     * export.
     *
     * The record is built from unsaved models by the same feature() the
     * export uses, so a column added there appears here too. The history
     * columns are left out — nothing to fill in — and the header lists the
     * values each fixed-choice column accepts.
     */
    public function template(): string
    {
        $allowed = [];
        foreach (DeedImportAnalyzer::ENUMS as $field => $column) {
            $allowed[$field] = DatabaseEnum::for($column);
        }
        // A sample value for a fixed-choice column: the first it allows.
        $choice = static fn (string $field): ?string => $allowed[$field][0] ?? null;

        $region = (new Region)->forceFill(['name_ar' => 'منطقة الرياض', 'name_en' => 'Riyadh']);
        $city = (new City)->forceFill(['name_ar' => 'الرياض', 'name_en' => 'Riyadh'])->setRelation('region', $region);
        $district = (new District)->forceFill(['name_ar' => 'الملقا', 'name_en' => 'Al Malqa'])->setRelation('city', $city);
        $plan = (new Plan)->forceFill(['plan_no' => '3120'])->setRelation('district', $district);

        $parcel = (new Parcel)->forceFill([
            'geo_id' => 'DEMO-0001',
            'parcel_no' => '15',
            'asset_type' => $choice('asset_type'),
            'land_transaction' => $choice('land_transaction'),
            'allocation_method' => $choice('allocation_method'),
            'fall_in' => $choice('fall_in'),
            'm_price' => 1500,
            'parcel_price' => 937500,
        ]);
        $parcel->setRelation('plan', $plan);
        $parcel->setRelation('parent', null);
        $parcel->setRelation('boundary', (new ParcelBoundary)->forceFill([
            'n_border' => 'شارع عرض 20 م', 'n_dim' => 25,
            's_border' => 'قطعة رقم 16', 's_dim' => 25,
            'e_border' => 'قطعة رقم 14', 'e_dim' => 25,
            'w_border' => 'ممر مشاة عرض 6 م', 'w_dim' => 25,
            'measured_area' => 625, 'matches_deed' => true, 'survey_date' => '2024-01-10',
        ])->setRelation('engineeringOffice', null));
        $parcel->setRelation('surveyDecisions', new Collection([(new SurveyDecision)->forceFill([
            'qrar_no' => '12345', 'report_no' => '678', 'qrar_source' => $choice('qrar_source'), 'folder' => '1',
        ])]));
        // Documents are uploaded on the site after the import; the sample
        // only names the file expected, so it carries no link.
        $parcel->setRelation('photos', new Collection([(new ParcelPhoto)->forceFill([
            'photo_type' => 'صك', 'original_name' => 'صك-410100000001.pdf',
        ])]));

        $owner = (new Owner)->forceFill([
            'name' => 'محمد عبدالله السالم', 'national_id' => '1098765432', 'phone' => '0555555555',
            'email' => 'owner@example.com', 'whatsapp' => '0555555555',
        ])->setRelation('pivot', (new Pivot)->forceFill(['ownership_share' => 100]));

        $deed = (new Deed)->forceFill([
            'deed_no' => '410100000001',
            'deed_date_hijri' => '1445-06-15',
            'deed_area' => 625,
            'deed_status' => $choice('deed_status'),
            'deed_class' => $choice('deed_class'),
        ])->setRelation('owners', new Collection([$owner]));

        // A 25 m square in Al Malqa, so the file opens as a map layer.
        [$x, $y, $dx, $dy] = [46.62, 24.80, 0.000247, 0.000226];
        $shape = ['area' => 625.0, 'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [[[
            [$x, $y], [$x + $dx, $y], [$x + $dx, $y + $dy], [$x, $y + $dy], [$x, $y],
        ]]]]];

        $this->slots = ['owners' => 3, 'survey' => 2, 'documents' => 2];
        $this->history = false;
        $feature = $this->feature($deed, $parcel, self::GROUPS, [0 => $shape]);
        $feature['id'] = null;

        return self::json([
            'type' => 'FeatureCollection',
            'name' => self::FORMAT,
            'sokuki' => [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'template' => true,
                'crs' => 'EPSG:4326',
                'layout' => 'flat',
                'slots' => $this->slots,
                'allowed_values' => $allowed,
            ],
            'features' => [$feature],
        ]);
    }

    /**
     * The most owners one deed has, and the most survey decisions and
     * documents one parcel has, among the records being exported — at least
     * one of each, so the columns are always there to fill in.
     *
     * @param  Builder<Deed>  $deeds
     * @param  Builder<Parcel>|null  $parcels
     * @return array{owners: int, survey: int, documents: int}
     */
    private function slotsFor(Builder $deeds, ?Builder $parcels): array
    {
        $most = static fn (string $table, string $key, mixed $in): int => (int) DB::query()
            ->fromSub(DB::table($table)->selectRaw('COUNT(*) AS n')->whereIn($key, $in)->groupBy($key), 'counts')
            ->max('n');

        $parcelSets = [(clone $deeds)->reorder()->select('deeds.parcel_id')];
        if ($parcels !== null) {
            $parcelSets[] = (clone $parcels)->reorder()->select('parcels.id');
        }
        $perParcel = static fn (string $table): int => max(array_map(
            static fn (Builder $in): int => $most($table, 'parcel_id', $in),
            $parcelSets
        ));

        return [
            'owners' => max(1, $most('deed_owners', 'deed_id', (clone $deeds)->reorder()->select('deeds.id'))),
            'survey' => max(1, $perParcel('survey_decisions')),
            'documents' => max(1, $perParcel('parcel_photos')),
        ];
    }

    /**
     * Eager loads for a parcel's chosen parts, so a chunk costs a handful of
     * queries rather than several per record. `$prefix` is 'parcel.' when
     * loading through a deed.
     *
     * @param  list<string>  $groups
     * @return array<int|string, mixed>
     */
    private function relations(array $groups, bool $archived, string $prefix): array
    {
        $relations = [
            $prefix.'plan.district.city.region',
            $prefix.'archivedBy',
            // The parent may be archived while its units are not; its GEO ID still names it.
            $prefix.'parent' => fn ($q) => $q->withTrashed(),
        ];

        if (in_array('boundary', $groups, true)) {
            $relations[] = $prefix.'boundary.engineeringOffice';
        }
        if (in_array('survey', $groups, true)) {
            $relations[] = $prefix.'surveyDecisions';
        }
        if (in_array('documents', $groups, true)) {
            $relations[] = $prefix.'photos.uploader';
            $relations[] = $prefix.'photos.reviewer';
        }

        return $relations;
    }

    /**
     * Polygon and computed area of each parcel, in one query.
     *
     * @param  list<int|string>  $parcelIds
     * @return array<int, array{geometry: mixed, area: float|null}>
     */
    private function geometries(array $parcelIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $parcelIds)));

        if ($ids === []) {
            return [];
        }

        $rows = DB::table('parcels')
            ->whereIn('id', $ids)
            ->whereNotNull('geom')
            ->selectRaw('id, ST_AsGeoJSON(geom, '.self::PRECISION.') AS g, '.Spatial::areaSqm('geom').' AS area')
            ->get();

        $geometries = [];
        foreach ($rows as $row) {
            $geometries[(int) $row->id] = [
                'geometry' => json_decode((string) $row->g, true),
                'area' => $row->area === null ? null : round((float) $row->area, 2),
            ];
        }

        return $geometries;
    }

    /**
     * One deed (or one deedless parcel) as a feature whose properties are
     * all plain values, in the order a person reads them: deed, parcel,
     * location, boundary, owners, survey decisions, documents.
     *
     * @param  list<string>  $groups
     * @param  array<int, array{geometry: mixed, area: float|null}>  $geometries
     * @return array<string, mixed>
     */
    private function feature(?Deed $deed, ?Parcel $parcel, array $groups, array $geometries): array
    {
        $plan = $parcel?->plan;
        $district = $plan?->district;
        $city = $district?->city;
        $region = $city?->region;
        $shape = $parcel === null ? null : ($geometries[(int) $parcel->id] ?? null);
        $has = static fn (string $group): bool => in_array($group, $groups, true);

        $p = [
            'deed_id' => $deed?->id,
            'deed_no' => $deed?->deed_no,
            'deed_date_hijri' => $deed?->deed_date_hijri,
            'deed_area' => self::number($deed?->deed_area),
            'deed_status' => self::scalar($deed?->deed_status),
            'deed_class' => self::scalar($deed?->deed_class),
        ] + $this->history('deed', $deed);

        $p += [
            'parcel_id' => $parcel?->id,
            'parcel_geo_id' => $parcel?->geo_id,
            'parcel_no' => $parcel?->parcel_no,
            'parent_geo_id' => $parcel?->parent?->geo_id,
        ];

        if ($has('parcel')) {
            $p += [
                'asset_type' => self::scalar($parcel?->asset_type),
                'land_transaction' => self::scalar($parcel?->land_transaction),
                'allocation_method' => self::scalar($parcel?->allocation_method),
                'fall_in' => self::scalar($parcel?->fall_in),
                'm_price' => self::number($parcel?->m_price),
                'parcel_price' => self::number($parcel?->parcel_price),
            ] + $this->history('parcel', $parcel);

            if ($this->history) {
                $p['parcel_source_gdb_id'] = $parcel?->source_gdb_id;
                $p['parcel_last_synced_at'] = self::date($parcel?->last_synced_at);
            }
        }

        if ($has('geometry')) {
            $p['computed_area_sqm'] = $shape['area'] ?? null;
        }

        $p += [
            'plan_no' => $plan?->plan_no,
            'district' => $district?->name_ar,
            'district_en' => $district?->name_en,
            'city' => $city?->name_ar,
            'city_en' => $city?->name_en,
            'region' => $region?->name_ar,
            'region_en' => $region?->name_en,
        ];

        if ($has('boundary')) {
            $boundary = $parcel?->boundary;
            $p += [
                'n_border' => $boundary?->n_border,
                'n_dim' => self::number($boundary?->n_dim),
                's_border' => $boundary?->s_border,
                's_dim' => self::number($boundary?->s_dim),
                'e_border' => $boundary?->e_border,
                'e_dim' => self::number($boundary?->e_dim),
                'w_border' => $boundary?->w_border,
                'w_dim' => self::number($boundary?->w_dim),
                'measured_area' => self::number($boundary?->measured_area),
                'matches_deed' => $boundary?->matches_deed,
                'survey_date' => $boundary?->survey_date,
                'engineering_office' => $boundary?->engineeringOffice?->name,
            ];
        }

        if ($has('owners')) {
            $owners = $deed === null ? [] : $deed->owners->values()->all();
            foreach ($this->numbered($owners, 'owners') as $n => $owner) {
                /** @var Owner|null $owner */
                $p += [
                    "owner_{$n}_id" => $owner?->id,
                    "owner_{$n}_name" => $owner?->name,
                    "owner_{$n}_national_id" => $owner?->national_id,
                    "owner_{$n}_phone" => $owner?->phone,
                    "owner_{$n}_email" => $owner?->email,
                    "owner_{$n}_whatsapp" => $owner?->whatsapp,
                    "owner_{$n}_share" => self::number($owner?->getRelationValue('pivot')?->getAttribute('ownership_share')),
                ] + $this->history("owner_{$n}", $owner, archivedBy: false);
            }
        }

        if ($has('survey')) {
            $decisions = $parcel === null ? [] : $parcel->surveyDecisions->values()->all();
            foreach ($this->numbered($decisions, 'survey') as $n => $decision) {
                /** @var SurveyDecision|null $decision */
                $p += [
                    "survey_{$n}_id" => $decision?->id,
                    "survey_{$n}_qrar_no" => $decision?->qrar_no,
                    "survey_{$n}_report_no" => $decision?->report_no,
                    "survey_{$n}_qrar_source" => self::scalar($decision?->qrar_source),
                    "survey_{$n}_folder" => $decision?->folder,
                ];
            }
        }

        if ($has('documents')) {
            // A deed scan belongs to one deed; every other document to the parcel.
            $documents = $parcel === null ? [] : $parcel->photos
                ->filter(fn (ParcelPhoto $photo): bool => $photo->deed_id === null || (int) $photo->deed_id === (int) $deed?->id)
                ->values()->all();
            foreach ($this->numbered($documents, 'documents') as $n => $photo) {
                /** @var ParcelPhoto|null $photo */
                $p += [
                    "document_{$n}_id" => $photo?->id,
                    "document_{$n}_type" => self::scalar($photo?->photo_type),
                    "document_{$n}_name" => $photo?->downloadName(),
                    "document_{$n}_status" => $photo?->status,
                    "document_{$n}_url" => $photo?->exists ? route('documents.download', $photo) : null,
                ];
                if ($this->history) {
                    $p += [
                        "document_{$n}_mime_type" => $photo?->mime_type,
                        "document_{$n}_size_bytes" => $photo?->size_bytes,
                        "document_{$n}_uploaded_by" => $photo?->uploader?->name,
                        "document_{$n}_uploaded_at" => self::date($photo?->created_at),
                        "document_{$n}_reviewed_by" => $photo?->reviewer?->name,
                        "document_{$n}_reviewed_at" => self::date($photo?->reviewed_at),
                        "document_{$n}_rejection_reason" => $photo?->rejection_reason,
                    ];
                }
            }
        }

        return [
            'type' => 'Feature',
            'id' => $deed?->id !== null ? 'deed-'.$deed->id : ($parcel?->id !== null ? 'parcel-'.$parcel->id : null),
            'geometry' => $shape['geometry'] ?? null,
            'properties' => $p,
        ];
    }

    /**
     * A list laid out over the file's numbered column sets, 1-based, padded
     * with nulls to the file's count. Never cut short: a record with more
     * than was counted keeps them all.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @param  'owners'|'survey'|'documents'  $kind
     * @return array<int, T|null>
     */
    private function numbered(array $items, string $kind): array
    {
        $slots = max($this->slots[$kind], count($items));
        $numbered = [];
        for ($n = 1; $n <= $slots; $n++) {
            $numbered[$n] = $items[$n - 1] ?? null;
        }

        return $numbered;
    }

    /**
     * When a record was created, last changed, archived, and by whom, as
     * `{prefix}_created_at` … — or nothing, when history is left out.
     *
     * @return array<string, mixed>
     */
    private function history(string $prefix, ?Model $record, bool $archivedBy = true): array
    {
        if (! $this->history) {
            return [];
        }

        $columns = [
            "{$prefix}_created_at" => self::date($record?->getAttribute('created_at')),
            "{$prefix}_updated_at" => self::date($record?->getAttribute('updated_at')),
            "{$prefix}_archived_at" => self::date($record?->getAttribute('deleted_at')),
        ];
        if ($archivedBy) {
            $columns["{$prefix}_archived_by"] = $record?->getRelationValue('archivedBy')?->getAttribute('name');
        }

        return $columns;
    }

    private static function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : ($value === null ? null : (string) $value);
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private static function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /** @param  array<mixed>  $data */
    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
