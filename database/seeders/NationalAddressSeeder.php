<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads every Saudi region, city and district from the National Address
 * (SPL) data in database/data/national-address, in Arabic and English.
 *
 * It adds to what is there rather than replacing it, and is safe to run
 * again:
 *
 *  - A record already loaded is recognised by its `national_address_id`.
 *  - A record entered by hand or imported with the survey data is matched
 *    by its Arabic name under the same parent — tolerant of hamza, taa
 *    marbuta and alif maqsura spellings and of a leading «حي» — then linked,
 *    and given its English name if it has none. Its Arabic name is never
 *    changed.
 *  - Everything else in the data is inserted.
 *
 * Existing records it cannot match (survey districts such as «كبد-1») are
 * left untouched and listed at the end, since no official source holds an
 * English name for them.
 */
class NationalAddressSeeder extends Seeder
{
    private const DATA = 'data/national-address';

    private const CHUNK = 500;

    /** @var array<string, array<string, int>> */
    private array $stats = [];

    public function run(): void
    {
        $regions = $this->load('regions');
        $cities = $this->load('cities');
        $districts = $this->load('districts');

        DB::transaction(function () use ($regions, $cities, $districts): void {
            $countryId = $this->country();

            $this->level('regions', $regions, 'country_id', static fn (): int => $countryId, capitals: []);

            // Of several same-named places, the one an existing record most
            // likely means is the one with districts, then a region capital.
            $districtCounts = $districts->countBy('city_id')->all();
            $capitals = $regions->pluck('capital_city_id')->flip()->all();

            $regionIds = $this->linkedIds('regions');
            $this->level('cities', $cities, 'region_id', static fn (array $row): ?int => $regionIds[$row['region_id']] ?? null,
                capitals: $capitals, weights: $districtCounts);

            $cityIds = $this->linkedIds('cities');
            $this->level('districts', $districts, 'city_id', static fn (array $row): ?int => $cityIds[$row['city_id']] ?? null, capitals: []);
        });

        $this->report();
    }

    /**
     * Link, complete and insert one level of the hierarchy.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  the source rows
     * @param  callable(array<string, mixed>): ?int  $localParent  local parent id of a source row
     * @param  array<int, mixed>  $capitals  source ids to prefer when a name is ambiguous
     * @param  array<int, int>  $weights  source id => how strongly to prefer it
     */
    private function level(string $table, Collection $rows, string $parentColumn, callable $localParent, array $capitals, array $weights = []): void
    {
        $this->stats[$table] = ['source' => $rows->count(), 'linked' => 0, 'english_added' => 0, 'inserted' => 0, 'unmatched_local' => 0];

        $linked = DB::table($table)->whereNotNull('national_address_id')->pluck('id', 'national_address_id')->all();

        // Source rows grouped by local parent and normalised name.
        $candidates = [];
        foreach ($rows as $row) {
            $parent = $localParent($row);
            if ($parent !== null && ! isset($linked[$row['id']])) {
                $candidates[$parent][self::normalise((string) $row['name_ar'])][] = $row;
            }
        }

        $byId = $rows->keyBy('id');

        // 1. Records already linked: only fill a missing English name.
        foreach (DB::table($table)->whereNotNull('national_address_id')->whereNull('name_en')->get() as $local) {
            $source = $byId[$local->national_address_id] ?? null;
            if ($source !== null) {
                $this->fillEnglish($table, (int) $local->id, (string) $source['name_en']);
            }
        }

        // 2. Records not linked yet: match by name under the same parent.
        foreach (DB::table($table)->whereNull('national_address_id')->orderBy('id')->get() as $local) {
            $key = self::normalise((string) $local->name_ar);
            $options = $candidates[$local->{$parentColumn}][$key] ?? [];

            if ($options === []) {
                $this->stats[$table]['unmatched_local']++;

                continue;
            }

            usort($options, static fn (array $a, array $b): int => [($weights[$b['id']] ?? 0), isset($capitals[$b['id']]), -$b['id']]
                <=> [($weights[$a['id']] ?? 0), isset($capitals[$a['id']]), -$a['id']]);
            $source = $options[0];

            DB::table($table)->where('id', $local->id)->update(['national_address_id' => $source['id'], 'updated_at' => now()]);
            $linked[$source['id']] = (int) $local->id;
            $this->stats[$table]['linked']++;

            if ($local->name_en === null || $local->name_en === '') {
                $this->fillEnglish($table, (int) $local->id, (string) $source['name_en']);
            }

            // One source record answers for one local record only.
            $candidates[$local->{$parentColumn}][$key] = array_values(array_filter(
                $options,
                static fn (array $row): bool => $row['id'] !== $source['id']
            ));
        }

        // 3. Everything else in the source is new.
        $now = now();
        $inserts = [];

        foreach ($rows as $row) {
            $parent = $localParent($row);

            if (isset($linked[$row['id']]) || $parent === null) {
                continue;
            }

            $inserts[] = [
                $parentColumn => $parent,
                'name_ar' => self::displayName($table, (string) $row['name_ar']),
                'name_en' => self::displayNameEn($table, (string) $row['name_en']),
                'national_address_id' => $row['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        $this->stats[$table]['inserted'] = count($inserts);
    }

    /** The Saudi Arabia country row, created or completed. */
    private function country(): int
    {
        $country = DB::table('countries')->where('iso_code', 'SA')->first()
            ?? DB::table('countries')->whereIn('name_ar', ['المملكة العربية السعودية', 'السعودية'])->first();

        if ($country === null) {
            return (int) DB::table('countries')->insertGetId([
                'name_ar' => 'المملكة العربية السعودية', 'name_en' => 'Saudi Arabia', 'iso_code' => 'SA',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('countries')->where('id', $country->id)->update([
            'name_en' => $country->name_en ?: 'Saudi Arabia',
            'iso_code' => $country->iso_code ?: 'SA',
            'updated_at' => now(),
        ]);

        return (int) $country->id;
    }

    /** @return array<int, int> source id => local id */
    private function linkedIds(string $table): array
    {
        return DB::table($table)->whereNotNull('national_address_id')
            ->pluck('id', 'national_address_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function fillEnglish(string $table, int $id, string $nameEn): void
    {
        DB::table($table)->where('id', $id)->update(['name_en' => self::displayNameEn($table, $nameEn), 'updated_at' => now()]);
        $this->stats[$table]['english_added']++;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function load(string $name): Collection
    {
        $path = database_path(self::DATA."/{$name}.json");
        $rows = json_decode((string) @file_get_contents($path), true);

        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException("Missing or unreadable data file: {$path}");
        }

        return collect($rows);
    }

    /**
     * The key two spellings of one name share: no «حي» prefix, no tatweel,
     * one alif, ه for ة, ي for ى, single spaces.
     */
    public static function normalise(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', str_replace('ـ', '', $name)) ?? $name);
        $name = preg_replace('/^حي\s+/u', '', $name) ?? $name;

        return strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
    }

    /**
     * Districts are stored without the «حي» the source puts before every
     * one, matching how the existing districts are written and how the
     * screens already print «الحي: …».
     */
    private static function displayName(string $table, string $name): string
    {
        return $table === 'districts' ? (preg_replace('/^حي\s+/u', '', trim($name)) ?? $name) : trim($name);
    }

    private static function displayNameEn(string $table, string $name): string
    {
        return $table === 'districts' ? (preg_replace('/\s+Dist\.?$/u', '', trim($name)) ?? $name) : trim($name);
    }

    private function report(): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->table(
            ['', 'source', 'linked to existing', 'English added', 'inserted', 'existing, no match'],
            collect($this->stats)->map(fn (array $s, string $table): array => [$table, ...array_values($s)])->values()->all()
        );

        foreach (['regions', 'cities', 'districts'] as $table) {
            $left = DB::table($table)->whereNull('national_address_id')->whereNull('name_en')->pluck('name_ar')->all();

            if ($left !== []) {
                $this->command->warn("{$table} still without an English name (not in the National Address data): ".implode('، ', $left));
            }
        }
    }
}
