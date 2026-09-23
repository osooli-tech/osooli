<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Connects to one of the two databases and reports what state it is in.
 *
 * Used both before saving connection details (to test values that are not
 * stored yet) and on the settings page (to show each database's health).
 */
final class ConnectionProbe
{
    /** Tables whose row counts the settings page shows side by side. */
    public const KEY_TABLES = ['parcels', 'deeds', 'owners', 'users', 'parcel_photos', 'audit_logs'];

    /** Name of the throwaway connection used to test unsaved details. */
    private const TRIAL = 'db_settings_trial';

    /**
     * Try a connection with the given details, without saving them.
     *
     * @param  array<string, mixed>  $details  form fields plus password
     * @return array{ok: bool, version: string|null, error: string|null}
     */
    public function trial(string $name, array $details): array
    {
        config(['database.connections.'.self::TRIAL => DatabaseSettings::merge(
            (array) config("database.connections.{$name}", []),
            $details
        )]);

        DB::purge(self::TRIAL);

        try {
            $connection = DB::connection(self::TRIAL);

            return ['ok' => true, 'version' => self::bounded(fn (): string => $this->version($connection)), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'version' => null, 'error' => self::describe($e)];
        } finally {
            DB::purge(self::TRIAL);
        }
    }

    /**
     * Health of a configured connection: reachable, which version, whether
     * its schema is up to date, and row counts of the main tables.
     *
     * @return array{ok: bool, version: string|null, error: string|null, migrated: bool, pending: int, counts: array<string, int|null>}
     */
    public function status(string $name): array
    {
        $status = ['ok' => false, 'version' => null, 'error' => null, 'migrated' => false, 'pending' => 0, 'counts' => []];

        try {
            // A fresh connection, so details saved a moment ago are used —
            // but never drop the default one this very request is running on.
            if ($name !== DB::getDefaultConnection()) {
                DB::purge($name);
            }
            $connection = DB::connection($name);
            $status['version'] = self::bounded(fn (): string => $this->version($connection));
            $status['ok'] = true;
        } catch (Throwable $e) {
            $status['error'] = self::describe($e);

            return $status;
        }

        $status['pending'] = count($this->pendingMigrations($name));
        $status['migrated'] = $connection->getSchemaBuilder()->hasTable('migrations') && $status['pending'] === 0;

        foreach (self::KEY_TABLES as $table) {
            try {
                $status['counts'][$table] = $connection->getSchemaBuilder()->hasTable($table)
                    ? $connection->table($table)->count()
                    : null;
            } catch (Throwable) {
                $status['counts'][$table] = null;
            }
        }

        return $status;
    }

    /**
     * Migrations on disk the connection has not run.
     *
     * @return list<string>
     */
    public function pendingMigrations(string $name): array
    {
        $files = array_keys($this->migrator()->getMigrationFiles([database_path('migrations')]));

        try {
            $ran = DB::connection($name)->getSchemaBuilder()->hasTable('migrations')
                ? DB::connection($name)->table('migrations')->pluck('migration')->all()
                : [];
        } catch (Throwable) {
            $ran = [];
        }

        return array_values(array_diff($files, $ran));
    }

    /**
     * Run a first contact with a server under a short read timeout as well
     * as the connect timeout: a host and port that answer but speak another
     * protocol (a MariaDB client pointed at PostgreSQL's port) would
     * otherwise wait a whole day for a greeting that never comes.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private static function bounded(callable $callback): mixed
    {
        $previous = ini_get('mysqlnd.net_read_timeout');
        ini_set('mysqlnd.net_read_timeout', (string) (DatabaseSettings::CONNECT_TIMEOUT * 2));

        try {
            return $callback();
        } finally {
            if ($previous !== false) {
                ini_set('mysqlnd.net_read_timeout', $previous);
            }
        }
    }

    private function migrator(): Migrator
    {
        return app('migrator');
    }

    private function version(Connection $connection): string
    {
        $version = (string) $connection->selectOne('SELECT VERSION() AS v')->v;

        // PostgreSQL's banner runs to a full sentence; the first two words say it.
        return $connection->getDriverName() === 'pgsql'
            ? implode(' ', array_slice(explode(' ', $version), 0, 2))
            : $version;
    }

    /**
     * A database error made fit to show: the driver's first line, with any
     * password that found its way into it removed.
     */
    public static function describe(Throwable $e): string
    {
        $message = strtok($e->getMessage(), "\n") ?: $e::class;
        $message = (string) preg_replace('/password=\S+/i', 'password=***', $message);

        // The query builder appends the full connection config after "(Connection: …".
        return trim((string) preg_replace('/\s*\(Connection:.*$/s', '', $message));
    }
}
