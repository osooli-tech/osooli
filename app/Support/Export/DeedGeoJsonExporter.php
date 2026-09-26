<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelBoundary;
use App\Models\ParcelPhoto;
use App\Models\SurveyDecision;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\DatabaseEnum;
use App\Support\Import\DeedImportAnalyzer;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes the deeds matching an export's filters to a GeoJSON file — and,
 * after them, the matching parcels that have no deed at all, so every
 * parcel on record can leave in the file.
 *
 * One feature per deed (or per deedless parcel), carrying the parcel's
 * polygon, so the file opens as a map layer in QGIS or ArcGIS. Its
 * properties hold two things:
 *
 *  - flat columns (deed number, parcel, location, owner names…) that a GIS
 *    attribute table shows as they are;
 *  - the full records, nested — every owner with their share, the
 *    boundaries, each survey decision, each document with a link to it —
 *    so nothing is lost and the file can be edited and imported back.
 *
 * Each record also carries a `meta` block — when it was created, last
 * changed, archived and by whom, and a document's size, uploader and
 * review. That is history, not data: the importer reads past it.
 *
 * Records are read in chunks and each feature is written as it is built, so
 * memory stays flat however many thousands match.
 */
final class DeedGeoJsonExporter
{
    public const FORMAT = 'sokuki-deeds';

    public const VERSION = 1;

    /** Optional parts of each feature; the deed itself is always included. */
    public const GROUPS = ['geometry', 'parcel', 'owners', 'boundary', 'survey', 'documents'];

    private const CHUNK = 500;

    /** Coordinate decimals kept — about a millimetre. */
    private const PRECISION = 8;

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
     * A file in the export's own layout with every column and no data: one
     * feature whose values are all empty, with one owner, one survey
     * decision and one document to show the shape of each list. Filled in
     * and uploaded, it imports like any export.
     *
     * Built by the same feature() the export uses, so a column added there
     * appears here without a second list to keep in step. The `meta` blocks
     * are left out — history the importer never reads — and the header
     * lists the values each fixed-choice column accepts.
     */
    public function template(): string
    {
        $deed = new Deed;
        $deed->setRelation('owners', new Collection([new Owner]));
        $deed->setRelation('archivedBy', null);

        $parcel = new Parcel;
        foreach (['plan' => null, 'parent' => null, 'archivedBy' => null, 'photos' => new Collection] as $relation => $value) {
            $parcel->setRelation($relation, $value);
        }
        $boundary = new ParcelBoundary;
        $boundary->setRelation('engineeringOffice', null);
        $parcel->setRelation('boundary', $boundary);
        $parcel->setRelation('surveyDecisions', new Collection([new SurveyDecision]));

        $feature = $this->feature($deed, $parcel, self::GROUPS, []);
        $feature['id'] = null;

        $properties = self::withoutMeta($feature['properties']);
        $properties['parcel']['location'] = array_fill_keys(['district', 'city', 'region'], ['name_ar' => null, 'name_en' => null]);
        $properties['documents'] = [['type' => null, 'name' => null, 'status' => null, 'url' => null]];
        // Records start out live; an empty template has nothing archived.
        array_walk_recursive($properties, static function (mixed &$value, int|string $key): void {
            if ($key === 'archived') {
                $value = null;
            }
        });
        $feature['properties'] = $properties;

        $allowed = [];
        foreach (DeedImportAnalyzer::ENUMS as $field => $column) {
            $allowed[$field] = DatabaseEnum::for($column);
        }

        return self::json([
            'type' => 'FeatureCollection',
            'name' => self::FORMAT,
            'sokuki' => [
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'template' => true,
                'crs' => 'EPSG:4326',
                'allowed_values' => $allowed,
            ],
            'features' => [$feature],
        ]);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function withoutMeta(array $data): array
    {
        unset($data['meta']);

        return array_map(static fn (mixed $value): mixed => is_array($value) ? self::withoutMeta($value) : $value, $data);
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
        $owners = $deed !== null && in_array('owners', $groups, true) ? $deed->owners : null;

        // Flat columns first: what a GIS attribute table shows directly.
        $properties = [
            'deed_id' => $deed?->id,
            'deed_no' => $deed?->deed_no,
            'deed_date_hijri' => $deed?->deed_date_hijri,
            'deed_area' => self::number($deed?->deed_area),
            'deed_status' => self::scalar($deed?->deed_status),
            'deed_class' => self::scalar($deed?->deed_class),
            'parcel_geo_id' => $parcel?->geo_id,
            'parcel_no' => $parcel?->parcel_no,
            'parent_geo_id' => $parcel?->parent?->geo_id,
            'plan_no' => $plan?->plan_no,
            'district' => $district?->name_ar,
            'city' => $city?->name_ar,
            'region' => $region?->name_ar,
        ];

        if ($owners !== null) {
            $properties['owners_names'] = $owners->pluck('name')->implode('، ') ?: null;
            $properties['owners_count'] = $owners->count();
        }

        if ($shape !== null) {
            $properties['computed_area_sqm'] = $shape['area'];
        }

        // Then the full records, nested. A deedless parcel has no deed block.
        $properties['deed'] = $deed === null ? null : [
            'id' => $deed->id,
            'deed_no' => $deed->deed_no,
            'deed_date_hijri' => $deed->deed_date_hijri,
            'deed_area' => self::number($deed->deed_area),
            'deed_status' => self::scalar($deed->deed_status),
            'deed_class' => self::scalar($deed->deed_class),
            'archived' => $deed->deleted_at !== null,
            'meta' => self::meta($deed),
        ];

        if (in_array('parcel', $groups, true) && $parcel !== null) {
            $properties['parcel'] = [
                'id' => $parcel->id,
                'geo_id' => $parcel->geo_id,
                'parcel_no' => $parcel->parcel_no,
                'parent_geo_id' => $parcel->parent?->geo_id,
                'asset_type' => self::scalar($parcel->asset_type),
                'land_transaction' => self::scalar($parcel->land_transaction),
                'allocation_method' => self::scalar($parcel->allocation_method),
                'fall_in' => self::scalar($parcel->fall_in),
                'm_price' => self::number($parcel->m_price),
                'parcel_price' => self::number($parcel->parcel_price),
                'computed_area_sqm' => $shape['area'] ?? null,
                'archived' => $parcel->deleted_at !== null,
                'plan' => ['plan_no' => $plan?->plan_no],
                'location' => [
                    'district' => self::place($district),
                    'city' => self::place($city),
                    'region' => self::place($region),
                ],
                'meta' => self::meta($parcel) + [
                    'source_gdb_id' => $parcel->source_gdb_id,
                    'last_synced_at' => self::date($parcel->last_synced_at),
                ],
            ];
        }

        if ($owners !== null) {
            $properties['owners'] = $owners->map(fn (Owner $owner): array => [
                'id' => $owner->id,
                'name' => $owner->name,
                'national_id' => $owner->national_id,
                'phone' => $owner->phone,
                'email' => $owner->email,
                'whatsapp' => $owner->whatsapp,
                'ownership_share' => self::number($owner->getRelationValue('pivot')?->getAttribute('ownership_share')),
                'archived' => $owner->deleted_at !== null,
                'meta' => [
                    'created_at' => self::date($owner->created_at),
                    'updated_at' => self::date($owner->updated_at),
                    'archived_at' => self::date($owner->deleted_at),
                ],
            ])->values()->all();
        }

        if (in_array('boundary', $groups, true)) {
            $boundary = $parcel?->boundary;
            $properties['boundary'] = $boundary === null ? null : [
                'north' => ['border' => $boundary->n_border, 'length' => self::number($boundary->n_dim)],
                'south' => ['border' => $boundary->s_border, 'length' => self::number($boundary->s_dim)],
                'east' => ['border' => $boundary->e_border, 'length' => self::number($boundary->e_dim)],
                'west' => ['border' => $boundary->w_border, 'length' => self::number($boundary->w_dim)],
                'measured_area' => self::number($boundary->measured_area),
                'matches_deed' => $boundary->matches_deed,
                'survey_date' => $boundary->survey_date,
                'engineering_office' => $boundary->engineeringOffice?->name,
            ];
        }

        if (in_array('survey', $groups, true)) {
            $properties['survey_decisions'] = ($parcel === null ? collect() : $parcel->surveyDecisions)->map(fn (SurveyDecision $decision): array => [
                'id' => $decision->id,
                'qrar_no' => $decision->qrar_no,
                'report_no' => $decision->report_no,
                'qrar_source' => self::scalar($decision->qrar_source),
                'folder' => $decision->folder,
            ])->values()->all();
        }

        if (in_array('documents', $groups, true)) {
            // A deed scan belongs to one deed; every other document to the parcel.
            $properties['documents'] = ($parcel === null ? collect() : $parcel->photos)
                ->filter(fn (ParcelPhoto $photo): bool => $photo->deed_id === null || (int) $photo->deed_id === (int) $deed?->id)
                ->map(fn (ParcelPhoto $photo): array => [
                    'id' => $photo->id,
                    'type' => self::scalar($photo->photo_type),
                    'name' => $photo->downloadName(),
                    'status' => $photo->status,
                    'url' => route('documents.download', $photo),
                    'meta' => [
                        'mime_type' => $photo->mime_type,
                        'size_bytes' => $photo->size_bytes,
                        'uploaded_by' => $photo->uploader?->name,
                        'uploaded_at' => self::date($photo->created_at),
                        'reviewed_by' => $photo->reviewer?->name,
                        'reviewed_at' => self::date($photo->reviewed_at),
                        'rejection_reason' => $photo->rejection_reason,
                    ],
                ])->values()->all();
        }

        return [
            'type' => 'Feature',
            'id' => $deed !== null ? 'deed-'.$deed->id : 'parcel-'.$parcel?->id,
            'geometry' => $shape['geometry'] ?? null,
            'properties' => $properties,
        ];
    }

    /**
     * When a deed or parcel was created, last changed, archived, and by whom.
     *
     * @return array<string, mixed>
     */
    private static function meta(Model $record): array
    {
        return [
            'created_at' => self::date($record->getAttribute('created_at')),
            'updated_at' => self::date($record->getAttribute('updated_at')),
            'archived_at' => self::date($record->getAttribute('deleted_at')),
            'archived_by' => $record->getRelationValue('archivedBy')?->getAttribute('name'),
        ];
    }

    /** @return array{name_ar: string, name_en: string|null}|null */
    private static function place(?Model $place): ?array
    {
        return $place === null ? null : [
            'name_ar' => (string) $place->getAttribute('name_ar'),
            'name_en' => $place->getAttribute('name_en'),
        ];
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
