<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Searchable pickers for the reference chain — country → region → city →
 * district.
 *
 * With the National Address loaded there are thousands of cities and
 * districts, far too many for a plain <select>, so pickers ask the server
 * for the few that match what was typed. Each label carries its parent
 * («النرجس — الرياض»), because two districts in different cities routinely
 * share a name.
 */
final class ReferenceOptions
{
    /** Picker sources: model, and the column that points at the parent. */
    public const SOURCES = [
        'countries' => [Country::class, null],
        'regions' => [Region::class, 'country_id'],
        'cities' => [City::class, 'region_id'],
        'districts' => [District::class, 'city_id'],
    ];

    /** How the label's parent is reached, per source. */
    private const PARENT_RELATION = [
        'countries' => null,
        'regions' => 'country',
        'cities' => 'region',
        'districts' => 'city',
    ];

    private const LIMIT = 30;

    /**
     * Records whose Arabic or English name contains `$term`, names that start
     * with it first.
     *
     * @return list<array{id: int, label: string}>
     */
    public static function search(string $source, string $term = '', ?int $parentId = null): array
    {
        [$model, $parentColumn] = self::SOURCES[$source];
        $term = trim($term);

        /** @var Builder<Model> $query */
        $query = $model::query();

        if ($parentColumn !== null && $parentId !== null) {
            $query->where($parentColumn, $parentId);
        }

        if ($term !== '') {
            $query->where(fn (Builder $q) => $q
                ->whereLike('name_ar', '%'.$term.'%')
                ->orWhereLike('name_en', '%'.$term.'%'))
                ->orderByRaw('CASE WHEN name_ar LIKE ? THEN 0 ELSE 1 END', [$term.'%']);
        }

        return $query->with(array_filter([self::PARENT_RELATION[$source]]))
            ->orderBy('name_ar')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Model $record): array => ['id' => (int) $record->getKey(), 'label' => self::labelFor($source, $record)])
            ->values()
            ->all();
    }

    /** The label of one record, for a picker that opens with a value already chosen. */
    public static function label(string $source, mixed $id): string
    {
        if ($id === null || $id === '' || ! isset(self::SOURCES[$source])) {
            return '';
        }

        $record = self::SOURCES[$source][0]::query()->find((int) $id);

        return $record === null ? '' : self::labelFor($source, $record);
    }

    private static function labelFor(string $source, Model $record): string
    {
        $name = (string) $record->getAttribute('name_ar');
        $relation = self::PARENT_RELATION[$source];
        $parent = $relation === null ? null : $record->getRelationValue($relation);
        $parentName = $parent instanceof Model ? (string) $parent->getAttribute('name_ar') : '';

        return $parentName !== '' ? $name.' — '.$parentName : $name;
    }
}
