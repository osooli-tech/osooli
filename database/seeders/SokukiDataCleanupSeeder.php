<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ArchivedValue;
use App\Models\Deed;
use App\Models\Parcel;
use App\Support\Database\Spatial;
use App\Support\Import\Normalise;
use App\Support\ParcelGeometry;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies the review of the survey data exported on 2026-09-25
 * (sokuki-deeds-20260925-091549), recorded in database/data/sokuki-cleanup.
 *
 * Nothing is lost:
 *  - Whole deeds and parcels are archived, not deleted — the archive screen
 *    restores them.
 *  - Every field it empties or corrects keeps its old value in
 *    `archived_values`, restorable from the same screen.
 *  - Every polygon it redraws keeps the old one in the parcel's geometry
 *    history.
 *
 * Each change names the value it expects to find (a deed number, a geo id,
 * the old field value). A record that no longer matches — edited by hand
 * since the export, or already cleaned by an earlier run — is skipped and
 * listed, so running it again changes nothing.
 *
 * Usage: php artisan db:seed --class=SokukiDataCleanupSeeder
 */
class SokukiDataCleanupSeeder extends Seeder
{
    private const DATA = 'data/sokuki-cleanup/fixes.json';

    /** The fixes file; the reviewed one unless a test points elsewhere. */
    public ?string $path = null;

    /** @var array<string, int> */
    private array $done = [];

    /** @var list<string> */
    private array $skipped = [];

    public function run(): void
    {
        $fixes = $this->load();

        DB::transaction(function () use ($fixes): void {
            $this->districts($fixes['districts']);
            $this->plans($fixes['plans']);
            $this->values($fixes['values']);
            $this->geometries($fixes['geometries']);
            $this->archiveDeeds($fixes['archive']['deeds']);
            $this->archiveParcels($fixes['archive']['parcels']);
        });

        $this->report();
    }

    /**
     * Place each survey district under the city and region its parcels are
     * actually in, creating the city when the reference data lacks it.
     *
     * @param  list<array{name_ar: string, name_en: string, city_ar: string|null, city_en: string|null, city_national_address_id: int|null, region_ar: string}>  $districts
     */
    private function districts(array $districts): void
    {
        foreach ($districts as $d) {
            $id = $this->find('districts', $d['name_ar'], self::surveyDistricts(...));

            // No city given: the district stays where it is and only gains
            // its English name.
            if ($d['city_ar'] === null) {
                if ($id !== null && DB::table('districts')->where('id', $id)->whereNull('name_en')
                    ->update(['name_en' => $d['name_en'], 'updated_at' => now()]) > 0) {
                    $this->count('district English name added');
                }

                continue;
            }

            $cityId = $this->city($d['region_ar'], $d['city_ar'], $d['city_en'], $d['city_national_address_id']);

            if ($id === null) {
                DB::table('districts')->insert([
                    'city_id' => $cityId, 'name_ar' => $d['name_ar'], 'name_en' => $d['name_en'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->count('district created');

                continue;
            }

            $row = DB::table('districts')->where('id', $id)->first(['city_id', 'name_en']);

            if ((int) $row->city_id !== $cityId
                && ArchivedValue::replace('districts', $id, 'city_id', (string) $row->city_id, (string) $cityId, 'نقل الحي إلى المدينة التي تقع فيها قطعه فعلاً')) {
                $this->count('district moved');
            }

            if ($row->name_en === null) {
                DB::table('districts')->where('id', $id)->update(['name_en' => $d['name_en'], 'updated_at' => now()]);
                $this->count('district English name added');
            }
        }
    }

    /**
     * Move parcels to the plan that carries their real district.
     *
     * A parcel outside any plan was attached to one shared «بدون» plan (or a
     * plan literally named «None»), and so to one district, wherever it lay.
     * Such parcels now go to a «بدون - <district>» plan per district.
     *
     * @param  list<array{parcel_id: int, geo_id: string, plan_no: string, district: string, reason: string}>  $plans
     */
    private function plans(array $plans): void
    {
        foreach ($plans as $p) {
            $parcel = DB::table('parcels')->where('id', $p['parcel_id'])->first(['geo_id', 'plan_id']);

            if ($parcel === null || $parcel->geo_id !== $p['geo_id']) {
                $this->skip("parcel {$p['parcel_id']} ({$p['geo_id']}): not found or geo id changed");

                continue;
            }

            $districtId = $this->find('districts', $p['district'], self::surveyDistricts(...));

            if ($districtId === null) {
                $this->skip("parcel {$p['geo_id']}: district {$p['district']} not found");

                continue;
            }

            $planId = DB::table('plans')->where('plan_no', $p['plan_no'])->value('id');

            if ($planId === null) {
                $planId = DB::table('plans')->insertGetId([
                    'plan_no' => $p['plan_no'], 'district_id' => $districtId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->count('plan created');
            } else {
                $this->planDistrict((int) $planId, $districtId, $p['reason']);
            }

            if ((int) $parcel->plan_id === (int) $planId) {
                continue;
            }

            if ($parcel->plan_id === null) {
                DB::table('parcels')->where('id', $p['parcel_id'])->update(['plan_id' => $planId, 'updated_at' => now()]);
            } else {
                ArchivedValue::replace('parcels', $p['parcel_id'], 'plan_id', (string) $parcel->plan_id, (string) $planId, $p['reason']);
            }
            $this->count('parcel moved to its district plan');
        }
    }

    private function planDistrict(int $planId, int $districtId, string $reason): void
    {
        $old = DB::table('plans')->where('id', $planId)->value('district_id');

        if ($old !== null && (int) $old === $districtId) {
            return;
        }

        if ($old === null) {
            DB::table('plans')->where('id', $planId)->update(['district_id' => $districtId, 'updated_at' => now()]);
        } else {
            ArchivedValue::replace('plans', $planId, 'district_id', (string) $old, (string) $districtId, $reason);
        }
        $this->count('plan district corrected');
    }

    /**
     * Empty or correct single fields, keeping the old value in the archive.
     *
     * @param  list<array{table: string, id: int, parcel_id?: int, field: string, from: string, to: string|null, reason: string}>  $values
     */
    private function values(array $values): void
    {
        foreach ($values as $v) {
            // The export names a boundary by its parcel, not by its own id.
            if (isset($v['parcel_id'])) {
                $v['id'] = (int) DB::table($v['table'])->where('parcel_id', $v['parcel_id'])->value('id');
            }

            if (ArchivedValue::replace($v['table'], $v['id'], $v['field'], $v['from'], $v['to'], $v['reason'])) {
                $this->count($v['to'] === null ? "{$v['table']}.{$v['field']} emptied" : "{$v['table']}.{$v['field']} corrected");
            } else {
                $this->skip("{$v['table']} {$v['id']} {$v['field']}: no longer «{$v['from']}»");
            }
        }
    }

    /**
     * Redraw polygons: repair invalid ones and trim slivers where two
     * parcels overlap along a shared edge. The replaced polygon stays in the
     * parcel's geometry history.
     *
     * @param  list<array{parcel_id: int, geo_id: string, before_area: float, geometry: array<string, mixed>, reason: string}>  $geometries
     */
    private function geometries(array $geometries): void
    {
        foreach ($geometries as $g) {
            $parcel = Parcel::withTrashed()->whereKey($g['parcel_id'])->lockForUpdate()->first();

            if ($parcel === null || $parcel->geo_id !== $g['geo_id']) {
                $this->skip("geometry {$g['geo_id']}: parcel not found or geo id changed");

                continue;
            }

            // Only the polygon the review looked at is replaced; one redrawn
            // since, or already cleaned, has a different area.
            $area = (float) DB::table('parcels')->where('id', $parcel->id)
                ->selectRaw(Spatial::areaSqm('geom').' AS a')->value('a');

            if (abs($area - $g['before_area']) > max(1.0, $g['before_area'] * 0.001)) {
                $this->skip("geometry {$g['geo_id']}: area is {$area}, expected {$g['before_area']}");

                continue;
            }

            ParcelGeometry::replace($parcel->id, json_encode($g['geometry'], JSON_THROW_ON_ERROR), 'cleanup');
            $this->count('polygon repaired');
        }
    }

    /** @param  list<array{id: int, deed_no: string|null, geo_id: string, reason: string}>  $deeds */
    private function archiveDeeds(array $deeds): void
    {
        foreach ($deeds as $d) {
            $deed = Deed::query()->with('parcel')->find($d['id']);

            if ($deed === null || $deed->deed_no !== $d['deed_no'] || $deed->parcel?->geo_id !== $d['geo_id']) {
                $this->skip("deed {$d['id']} ({$d['deed_no']}): not found, already archived, or changed");

                continue;
            }

            $deed->archive();
            $this->count('deed archived');
        }
    }

    /** @param  list<array{id: int, geo_id: string, reason: string}>  $parcels */
    private function archiveParcels(array $parcels): void
    {
        foreach ($parcels as $p) {
            $parcel = Parcel::query()->find($p['id']);

            if ($parcel === null || $parcel->geo_id !== $p['geo_id']) {
                $this->skip("parcel {$p['id']} ({$p['geo_id']}): not found, already archived, or changed");

                continue;
            }

            // A parcel is archived only once nothing live hangs off it.
            if (Deed::query()->where('parcel_id', $parcel->id)->exists()) {
                $this->skip("parcel {$p['geo_id']}: still has live deeds");

                continue;
            }

            $parcel->archive();
            $this->count('parcel archived');
        }
    }

    /**
     * Survey districts are the ones no official list holds. Official ones are
     * left alone, even when one happens to share a survey district's name.
     */
    private static function surveyDistricts(Builder $query): void
    {
        $query->whereNull('national_address_id');
    }

    /**
     * The governorate city, by its National Address id when the reference
     * data is loaded (the region holds other places of the same name), else
     * by name, else created.
     */
    private function city(string $region, string $name, string $nameEn, ?int $nationalAddressId): int
    {
        $regionId = $this->find('regions', $region)
            ?? throw new RuntimeException("Region {$region} not found.");

        $id = $nationalAddressId === null ? null
            : DB::table('cities')->where('national_address_id', $nationalAddressId)->value('id');

        $id ??= $this->find('cities', $name, static fn ($q) => $q->where('region_id', $regionId));

        if ($id !== null) {
            return $id;
        }

        $this->count('city created');

        return DB::table('cities')->insertGetId([
            'region_id' => $regionId, 'name_ar' => $name, 'name_en' => $nameEn,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * The one row of `$table` whose Arabic name matches `$name` however it is
     * spelt, or null. Several matches are an error: guessing would move the
     * wrong record.
     *
     * @param  (callable(Builder): mixed)|null  $scope
     */
    private function find(string $table, string $name, ?callable $scope = null): ?int
    {
        $query = DB::table($table)->select(['id', 'name_ar']);
        if ($scope !== null) {
            $scope($query);
        }

        $key = Normalise::arabic($name);
        $ids = $query->get()->filter(static fn ($row): bool => Normalise::arabic((string) $row->name_ar) === $key)->pluck('id');

        if ($ids->count() > 1) {
            throw new RuntimeException("More than one row in {$table} is named {$name}.");
        }

        return $ids->isEmpty() ? null : (int) $ids->first();
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        $path = $this->path ?? database_path(self::DATA);

        if (! is_file($path)) {
            throw new RuntimeException("Missing {$path}.");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function count(string $what): void
    {
        $this->done[$what] = ($this->done[$what] ?? 0) + 1;
    }

    private function skip(string $why): void
    {
        $this->skipped[] = $why;
    }

    private function report(): void
    {
        if ($this->command === null) {
            return;
        }

        foreach ($this->done as $what => $n) {
            $this->command->info(sprintf('%5d  %s', $n, $what));
        }

        if ($this->skipped !== []) {
            $this->command->warn(count($this->skipped).' skipped:');
            foreach ($this->skipped as $line) {
                $this->command->line('  - '.$line);
            }
        }
    }
}
