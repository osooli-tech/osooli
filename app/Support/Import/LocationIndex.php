<?php

declare(strict_types=1);

namespace App\Support\Import;

use Illuminate\Support\Facades\DB;

/**
 * Regions, cities, districts and plans in memory, looked up by name the
 * way a person writes them — «الرياض», «منطقة الرياض», «حي الملقا» and
 * «الملقا» all find the same record.
 */
final class LocationIndex
{
    /** @var array<string, list<int>> */
    private array $regions = [];

    /** @var array<string, list<array{id: int, region_id: int}>> */
    private array $cities = [];

    /** @var array<int, array<string, int>> city id => name => district id */
    private array $districts = [];

    /** @var array<int, array<int, string>> city id => district id => Arabic name */
    private array $districtNames = [];

    /** @var array<string, array{id: int, district_id: int|null}> */
    private array $plans = [];

    public function __construct()
    {
        foreach (DB::table('regions')->get(['id', 'name_ar']) as $row) {
            $this->regions[Normalise::arabic((string) $row->name_ar)][] = (int) $row->id;
        }
        foreach (DB::table('cities')->get(['id', 'region_id', 'name_ar']) as $row) {
            $this->cities[Normalise::arabic((string) $row->name_ar)][] = ['id' => (int) $row->id, 'region_id' => (int) $row->region_id];
        }
        foreach (DB::table('districts')->get(['id', 'city_id', 'name_ar']) as $row) {
            $this->districts[(int) $row->city_id][Normalise::arabic((string) $row->name_ar)] ??= (int) $row->id;
            $this->districtNames[(int) $row->city_id][(int) $row->id] = (string) $row->name_ar;
        }
        foreach (DB::table('plans')->get(['id', 'plan_no', 'district_id']) as $row) {
            $this->plans[trim((string) $row->plan_no)] = ['id' => (int) $row->id, 'district_id' => $row->district_id === null ? null : (int) $row->district_id];
        }
    }

    /**
     * The city a region/city pair names, or why it cannot be found.
     *
     * @return array{id: int|null, error: string|null}
     */
    public function city(?string $region, string $city): array
    {
        $options = $this->cities[Normalise::arabic($city)] ?? [];

        if ($region !== null) {
            $regionIds = $this->regions[Normalise::arabic($region)] ?? [];
            if ($regionIds === []) {
                return ['id' => null, 'error' => 'region_not_found'];
            }
            $options = array_values(array_filter($options, static fn (array $c): bool => in_array($c['region_id'], $regionIds, true)));
        }

        return match (count($options)) {
            0 => ['id' => null, 'error' => 'city_not_found'],
            1 => ['id' => $options[0]['id'], 'error' => null],
            // Villages share names across regions; the region settles it.
            default => ['id' => null, 'error' => 'city_ambiguous'],
        };
    }

    public function district(int $cityId, string $name): ?int
    {
        return $this->districts[$cityId][Normalise::arabic($name)] ?? null;
    }

    /**
     * Districts of the city whose names are close to `$name`, for the
     * "did you mean" choice.
     *
     * @return list<array{id: int, label: string}>
     */
    public function districtSuggestions(int $cityId, string $name, int $limit = 5): array
    {
        $key = Normalise::arabic($name);
        $scored = [];

        foreach ($this->districtNames[$cityId] ?? [] as $id => $label) {
            $other = Normalise::arabic($label);
            $distance = str_contains($other, $key) || str_contains($key, $other) ? 0 : levenshtein($key, $other);
            if ($distance <= 6) {
                $scored[] = ['id' => $id, 'label' => $label, 'distance' => $distance];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        return array_map(static fn (array $s): array => ['id' => $s['id'], 'label' => $s['label']], array_slice($scored, 0, $limit));
    }

    /** @return array{id: int, district_id: int|null}|null */
    public function plan(string $planNo): ?array
    {
        return $this->plans[trim($planNo)] ?? null;
    }
}
