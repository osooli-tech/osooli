<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads the allowed labels of a PostgreSQL enum type straight from the database.
 *
 * Dropdowns are built from this rather than from the PHP enums in App\Enums,
 * because those only cover five of the seven constrained columns — `fall_in`
 * and `allocation_method` have no PHP enum at all, and `fall_in` has gained
 * three values since it was created. A list read from pg_enum cannot drift
 * from what the column will actually accept, which is what makes an invalid
 * selection impossible rather than merely rejected on save.
 */
final class DatabaseEnum
{
    /** Enum types this application exposes as form dropdowns. */
    public const TYPES = [
        'asset_type' => 'asset_type_enum',
        'land_transaction' => 'land_transaction_enum',
        'allocation_method' => 'allocation_method_enum',
        'fall_in' => 'fall_in_enum',
        'deed_status' => 'deed_status_enum',
        'deed_class' => 'deed_class_enum',
        'qrar_source' => 'qrar_source_enum',
        'photo_type' => 'photo_type_enum',
    ];

    private const CACHE_TTL = 3600;

    /**
     * Allowed labels for a column, in the order Postgres declares them.
     *
     * @return list<string>
     */
    public static function for(string $column): array
    {
        $type = self::TYPES[$column] ?? null;

        if ($type === null) {
            return [];
        }

        return self::labels($type);
    }

    /**
     * Allowed labels for an enum type name.
     *
     * @return list<string>
     */
    public static function labels(string $type): array
    {
        /** @var list<string> */
        return Cache::remember(
            "db-enum:{$type}",
            self::CACHE_TTL,
            static fn (): array => array_map(
                static fn (object $row): string => (string) $row->label,
                DB::select(
                    'SELECT e.enumlabel AS label
                       FROM pg_enum e
                       JOIN pg_type t ON t.oid = e.enumtypid
                      WHERE t.typname = ?
                   ORDER BY e.enumsortorder',
                    [$type]
                )
            )
        );
    }

    /**
     * A validation rule that accepts only what the column accepts.
     *
     * Using this instead of a hand-written `in:` list means a value added to
     * the type by a migration is accepted immediately, with no second place
     * to remember to update.
     */
    public static function rule(string $column): string
    {
        $labels = self::for($column);

        return $labels === [] ? 'string' : 'in:'.implode(',', $labels);
    }

    /** Drops the cached labels for one type, or for all of them. */
    public static function forget(?string $type = null): void
    {
        foreach ($type !== null ? [$type] : array_values(self::TYPES) as $name) {
            Cache::forget("db-enum:{$name}");
        }
    }
}
