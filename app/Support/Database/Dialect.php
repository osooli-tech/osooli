<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Which SQL dialect a connection speaks.
 *
 * The application runs on PostgreSQL/PostGIS or on MariaDB, whichever the
 * database settings name as primary. Code that has to write SQL the two
 * disagree on asks here rather than reading `database.default`, because the
 * migrator and the sync command point the default connection elsewhere for
 * the length of a run.
 */
final class Dialect
{
    public static function isPostgres(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'pgsql';
    }

    public static function isMaria(?string $connection = null): bool
    {
        return in_array(DB::connection($connection)->getDriverName(), ['mariadb', 'mysql'], true);
    }

    /** Whether the connection can run the spatial queries at all (not SQLite). */
    public static function isSpatial(?string $connection = null): bool
    {
        return self::isPostgres($connection) || self::isMaria($connection);
    }
}
