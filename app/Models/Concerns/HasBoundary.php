<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * A reference record — district, city, region, country — that carries its
 * administrative boundary in a `geom` column.
 *
 * A region's boundary runs to hundreds of kilobytes, and these records are
 * loaded everywhere: as a parcel's district, a plan's city, a filter's
 * options. So a `select *` never reads the polygon; the columns are spelled
 * out without it. The boundary is read by the code that needs it, in SQL of
 * its own (ST_AsGeoJSON, ST_Contains), never through the model.
 */
trait HasBoundary
{
    /** @var array<string, list<string>> table => columns other than geom */
    private static array $columnsWithoutBoundary = [];

    public static function bootHasBoundary(): void
    {
        static::addGlobalScope('without_boundary', static function (Builder $query): void {
            $base = $query->getQuery();
            $table = $query->getModel()->getTable();
            $columns = self::columnsWithoutBoundary($table);

            if ($base->columns === null) {
                $query->select($columns);

                return;
            }

            // A bare `*` is only this table's when nothing is joined in.
            $bare = $base->joins === null || $base->joins === [];

            $base->columns = array_merge(...array_map(
                static fn (mixed $column): array => $column === $table.'.*' || ($bare && $column === '*') ? $columns : [$column],
                $base->columns
            ));
        });
    }

    /** @return list<string> qualified with the table name */
    private static function columnsWithoutBoundary(string $table): array
    {
        return self::$columnsWithoutBoundary[$table] ??= array_values(array_map(
            static fn (string $column): string => $table.'.'.$column,
            array_filter(Schema::getColumnListing($table), static fn (string $column): bool => $column !== 'geom')
        ));
    }
}
