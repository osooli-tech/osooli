<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\SurveyDecision;
use App\Models\User;
use App\Support\Database\Spatial;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Writes the deeds matching an export's filters to a GeoJSON file.
 *
 * One feature per deed, carrying its parcel's polygon, so the file opens as
 * a map layer in QGIS or ArcGIS. Its properties hold two things:
 *
 *  - flat columns (deed number, parcel, location, owner names…) that a GIS
 *    attribute table shows as they are;
 *  - the full records, nested — every owner with their share, the
 *    boundaries, each survey decision, each document with a link to it —
 *    so nothing is lost and the file can be edited and imported back.
 *
 * The keys that identify a record on import (`parcel.geo_id`, `deed.id`,
 * `owners[].national_id` …) are always present, and the header names the
 * format and its version so a later importer knows how to read it.
 *
 * Deeds are read in chunks and each feature is written as it is built, so
 * memory stays flat however many thousands of deeds match.
 */
final class DeedGeoJsonExporter
{
    public const FORMAT = 'sokuki-deeds';

    public const VERSION = 1;

    /** Optional parts of each feature; the deed itself is always included. */
    public const GROUPS = ['geometry', 'parcel', 'owners', 'boundary', 'survey', 'documents'];

    private const CHUNK = 500;

    /** Coordinate decimals kept — about a centimetre. */
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
        // Large files: the owner index and per-file bookkeeping grow with them.
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $groups = array_values(array_intersect(self::GROUPS, $groups));
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
            'bytes' => 0,
            'error' => null,
        ];
        ExportRuns::save($run);

        $path = ExportRuns::filePath($id);
        $partial = $path.'.part';

        try {
            $query = $filters->query($user);
            $run['total'] = (clone $query)->count();
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

            $query->with($this->relations($groups, $filters->includeArchived()))
                ->chunkById(self::CHUNK, function (Collection $deeds) use ($handle, $groups, &$first, &$run): void {
                    $geometries = in_array('geometry', $groups, true) ? $this->geometries($deeds) : [];

                    foreach ($deeds as $deed) {
                        /** @var Deed $deed */
                        fwrite($handle, ($first ? '' : ',')."\n".self::json($this->feature($deed, $groups, $geometries)));
                        $first = false;
                    }

                    $run['done'] += $deeds->count();
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
     * Eager loads for the chosen parts, so a chunk costs a handful of
     * queries rather than several per deed.
     *
     * @param  list<string>  $groups
     * @return array<string|int, mixed>
     */
    private function relations(array $groups, bool $archived): array
    {
        $withArchived = static fn ($q) => $archived ? $q->withTrashed() : $q;

        $relations = [
            'parcel' => $withArchived,
            'parcel.plan.district.city.region',
        ];

        if (in_array('owners', $groups, true)) {
            $relations['owners'] = $withArchived;
        }
        if (in_array('boundary', $groups, true)) {
            $relations[] = 'parcel.boundary.engineeringOffice';
        }
        if (in_array('survey', $groups, true)) {
            $relations[] = 'parcel.surveyDecisions';
        }
        if (in_array('documents', $groups, true)) {
            $relations[] = 'parcel.photos';
        }

        return $relations;
    }

    /**
     * Polygon and computed area of each parcel in the chunk, in one query.
     *
     * @param  Collection<int, Deed>  $deeds
     * @return array<int, array{geometry: mixed, area: float|null}>
     */
    private function geometries(Collection $deeds): array
    {
        $ids = $deeds->pluck('parcel_id')->unique()->values()->all();

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
    private function feature(Deed $deed, array $groups, array $geometries): array
    {
        /** @var Parcel|null $parcel */
        $parcel = $deed->parcel;
        $plan = $parcel?->plan;
        $district = $plan?->district;
        $city = $district?->city;
        $region = $city?->region;
        $shape = $geometries[(int) $deed->parcel_id] ?? null;
        $owners = in_array('owners', $groups, true) ? $deed->owners : null;

        // Flat columns first: what a GIS attribute table shows directly.
        $properties = [
            'deed_id' => $deed->id,
            'deed_no' => $deed->deed_no,
            'deed_date_hijri' => $deed->deed_date_hijri,
            'deed_area' => self::number($deed->deed_area),
            'deed_status' => self::scalar($deed->deed_status),
            'deed_class' => self::scalar($deed->deed_class),
            'parcel_geo_id' => $parcel?->geo_id,
            'parcel_no' => $parcel?->parcel_no,
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

        // Then the full records, nested.
        $properties['deed'] = [
            'id' => $deed->id,
            'deed_no' => $deed->deed_no,
            'deed_date_hijri' => $deed->deed_date_hijri,
            'deed_area' => self::number($deed->deed_area),
            'deed_status' => self::scalar($deed->deed_status),
            'deed_class' => self::scalar($deed->deed_class),
            'archived' => $deed->deleted_at !== null,
        ];

        if (in_array('parcel', $groups, true) && $parcel !== null) {
            $properties['parcel'] = [
                'id' => $parcel->id,
                'geo_id' => $parcel->geo_id,
                'parcel_no' => $parcel->parcel_no,
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
                ->filter(fn (ParcelPhoto $photo): bool => $photo->deed_id === null || (int) $photo->deed_id === (int) $deed->id)
                ->map(fn (ParcelPhoto $photo): array => [
                    'id' => $photo->id,
                    'type' => self::scalar($photo->photo_type),
                    'name' => $photo->downloadName(),
                    'status' => $photo->status,
                    'url' => route('documents.download', $photo),
                ])->values()->all();
        }

        return [
            'type' => 'Feature',
            'id' => 'deed-'.$deed->id,
            'geometry' => $shape['geometry'] ?? null,
            'properties' => $properties,
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
