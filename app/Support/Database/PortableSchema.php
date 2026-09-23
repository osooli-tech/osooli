<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * The schema pieces migrations used to write as raw PostgreSQL, written once
 * for each dialect so every migration builds the same tables on PostgreSQL
 * and on MariaDB.
 *
 * Two deliberate differences on MariaDB:
 *
 *  - A named Postgres enum type becomes a VARCHAR column. MariaDB has no
 *    named enum types, and an inline ENUM(...) would have to be rewritten on
 *    every table using it each time a value is added. The allowed values are
 *    enforced by the application's validation, as they already are for the
 *    forms, and a sync copies the values across verbatim either way.
 *  - The geometry column carries no spatial index: MariaDB only indexes
 *    NOT NULL geometry, and a parcel may have no polygon yet. At a few
 *    thousand parcels a scan costs nothing measurable.
 *
 * Each method reads the dialect of the default connection, which the migrator
 * points at whichever database it is migrating.
 */
final class PortableSchema
{
    /** Length of the VARCHAR that stands in for an enum type on MariaDB. */
    private const ENUM_LENGTH = 100;

    /** @param  list<string>  $values */
    public static function createEnumType(string $type, array $values): void
    {
        if (! Dialect::isPostgres()) {
            return;
        }

        $literals = implode(', ', array_map(self::quote(...), $values));

        // Postgres has no CREATE TYPE IF NOT EXISTS, and enum types survive
        // the table drops RefreshDatabase performs — so swallow duplicates.
        DB::statement("DO $$ BEGIN
            CREATE TYPE {$type} AS ENUM ({$literals});
        EXCEPTION WHEN duplicate_object THEN NULL; END $$;");
    }

    public static function addEnumValue(string $type, string $value): void
    {
        if (Dialect::isPostgres()) {
            // IF NOT EXISTS keeps this replayable — enum types outlive the
            // table drops that RefreshDatabase performs between test runs.
            DB::statement("ALTER TYPE {$type} ADD VALUE IF NOT EXISTS ".self::quote($value));
        }
    }

    public static function dropEnumType(string $type): void
    {
        if (Dialect::isPostgres()) {
            DB::statement("DROP TYPE IF EXISTS {$type}");
        }
    }

    /** Adds a column of a named enum type (a VARCHAR on MariaDB). */
    public static function addEnumColumn(string $table, string $column, string $type, bool $nullable = true, ?string $default = null): void
    {
        $sqlType = Dialect::isPostgres() ? $type : 'VARCHAR('.self::ENUM_LENGTH.')';

        DB::statement(trim(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s %s %s',
            $table,
            $column,
            $sqlType,
            $nullable ? 'NULL' : 'NOT NULL',
            $default === null ? '' : 'DEFAULT '.self::quote($default),
        )));
    }

    /** Adds a MultiPolygon column in SRID 4326, indexed where the database allows. */
    public static function addGeometryColumn(string $table, string $column = 'geom', bool $index = true): void
    {
        if (Dialect::isPostgres()) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} geometry(MultiPolygon, 4326)");

            if ($index) {
                DB::statement("CREATE INDEX idx_{$table}_{$column} ON {$table} USING GIST({$column})");
            }

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} MULTIPOLYGON NULL");
    }

    /** Drops an index by name, wherever the dialect keeps it. */
    public static function dropIndex(string $table, string $index): void
    {
        DB::statement(Dialect::isPostgres()
            ? "DROP INDEX IF EXISTS {$index}"
            : "DROP INDEX IF EXISTS {$index} ON {$table}");
    }

    private static function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
