<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Makes one database an exact copy of the other.
 *
 * A sync is a full mirror, not an incremental merge: every copied table in
 * the target is emptied and refilled from the source. At this application's
 * size that takes seconds, and it is the only approach that also carries
 * deletions and needs no `updated_at` on every table.
 *
 * The run is built so a failure leaves the target exactly as it was:
 *
 *  1. The target's schema is brought up to date from the migrations — or,
 *     when it has no schema at all or a rebuild is asked for, dropped and
 *     rebuilt from scratch. Both databases are defined by the same
 *     migrations, so their tables always match.
 *  2. The source is read inside one snapshot, so rows written while the sync
 *     runs cannot leave a child row pointing at a parent it did not copy.
 *  3. All writes to the target happen in a single transaction, in foreign
 *     key order, and row counts are compared before it commits. Anything
 *     wrong rolls the whole copy back.
 */
final class DatabaseSynchronizer
{
    /**
     * Framework plumbing that belongs to each database on its own: the
     * migration log, cache, queue, and PostGIS's reference table.
     */
    public const EXCLUDED = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'spatial_ref_sys'];

    /** Rows per INSERT statement. */
    private const BATCH = 200;

    /** Decimal places kept when a geometry crosses over as GeoJSON — full double precision. */
    private const GEOJSON_PRECISION = 15;

    /** @var array<string, mixed> */
    private array $run = [];

    public function __construct(
        private readonly DatabaseSettings $settings,
        private readonly ConnectionProbe $probe,
    ) {}

    /**
     * Copy everything from `$from` into `$to`.
     *
     * @return array<string, mixed> the finished run, as the journal records it
     */
    public function run(string $from, string $to, bool $fresh = false, string $trigger = 'console', ?int $userId = null): array
    {
        if ($from === $to || ! in_array($from, DatabaseSettings::CONNECTIONS, true) || ! in_array($to, DatabaseSettings::CONNECTIONS, true)) {
            throw new RuntimeException("Cannot sync [{$from}] into [{$to}].");
        }

        $lock = SyncJournal::lock();

        if ($lock === null) {
            throw new RuntimeException('A sync is already running.');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $this->run = [
            'id' => bin2hex(random_bytes(6)),
            'state' => 'running',
            'from' => $from,
            'to' => $to,
            'fresh' => $fresh,
            'trigger' => $trigger,
            'user_id' => $userId,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'step' => 'connecting',
            'table' => null,
            'done' => 0,
            'total' => 0,
            'rows' => 0,
            'tables' => [],
            'schema' => null,
            'error' => null,
        ];
        $this->progress('connecting');

        try {
            $this->copy($from, $to, $fresh);

            $this->run['state'] = 'succeeded';
        } catch (Throwable $e) {
            report($e);

            $this->run['state'] = 'failed';
            $this->run['error'] = ConnectionProbe::describe($e);
        } finally {
            $this->run['finished_at'] = now()->toIso8601String();
            $this->run['step'] = 'finished';
            SyncJournal::finish($this->run);

            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $this->run;
    }

    private function copy(string $from, string $to, bool $fresh): void
    {
        DB::purge($to);
        $source = DB::connection($from);
        $target = DB::connection($to);

        // Both must answer before anything is touched.
        $source->getPdo();
        $target->getPdo();

        $pending = $this->probe->pendingMigrations($from);

        if ($pending !== []) {
            throw new RuntimeException('The source database has '.count($pending).' migration(s) not yet run; migrate it first.');
        }

        $this->prepareSchema($target, $fresh);

        $tables = $this->tablesToCopy($source, $target);
        [$order, $selfReferences] = $this->dependencyOrder($target, $tables);

        $this->run['total'] = count($order);

        $this->beginSnapshot($source);
        $target->beginTransaction();

        try {
            if (Dialect::isMaria($to)) {
                // Order already satisfies every constraint but the ones a
                // table has on itself; this is belt and braces for MariaDB.
                $target->statement('SET FOREIGN_KEY_CHECKS = 0');
            }

            $this->progress('clearing');
            foreach (array_reverse($order) as $table) {
                $target->table($table)->delete();
            }

            foreach ($order as $index => $table) {
                $this->run['table'] = $table;
                $this->run['done'] = $index;
                $this->progress('copying');

                $copied = $this->copyTable($source, $target, $table, $selfReferences[$table] ?? []);

                $this->run['tables'][$table] = $copied;
                $this->run['rows'] += $copied;
            }

            $this->run['table'] = null;
            $this->run['done'] = count($order);
            $this->progress('verifying');
            $this->verifyCounts($source, $target, $order);

            if (Dialect::isPostgres($to)) {
                $this->resetSequences($target, $order);
            }

            // The target's cache holds whatever was cached when it was last
            // primary — permissions included. Stale entries must not survive.
            foreach (['cache', 'cache_locks'] as $table) {
                if ($target->getSchemaBuilder()->hasTable($table)) {
                    $target->table($table)->delete();
                }
            }

            if (Dialect::isMaria($to)) {
                $target->statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $target->commit();
        } catch (Throwable $e) {
            $target->rollBack();

            if (Dialect::isMaria($to)) {
                try {
                    $target->statement('SET FOREIGN_KEY_CHECKS = 1');
                } catch (Throwable) {
                    // The connection is gone; the setting went with it.
                }
            }

            throw $e;
        } finally {
            $source->rollBack();
        }
    }

    /**
     * Bring the target's tables in line with the migrations, rebuilding from
     * nothing when there is no schema to build on or a rebuild was asked for.
     */
    private function prepareSchema(Connection $target, bool $fresh): void
    {
        $rebuild = $fresh || ! $target->getSchemaBuilder()->hasTable('migrations');

        $this->run['schema'] = $rebuild ? 'rebuilt' : 'migrated';
        $this->progress($rebuild ? 'rebuilding' : 'migrating');

        $exitCode = Artisan::call($rebuild ? 'migrate:fresh' : 'migrate', array_filter([
            '--database' => $target->getName(),
            '--force' => true,
            '--drop-views' => $rebuild ?: null,
        ]));

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Migrating the target database failed.');
        }
    }

    /** @return list<string> */
    private function tablesToCopy(Connection $source, Connection $target): array
    {
        $excluded = self::EXCLUDED;

        if (! $this->settings->sync()['include_sessions']) {
            $excluded[] = 'sessions';
        }

        $inTarget = $this->tableNames($target);

        return array_values(array_filter(
            $this->tableNames($source),
            static fn (string $table): bool => ! in_array($table, $excluded, true) && in_array($table, $inTarget, true)
        ));
    }

    /** @return list<string> */
    private function tableNames(Connection $connection): array
    {
        $tables = $connection->getSchemaBuilder()->getTables();

        if ($connection->getDriverName() === 'pgsql') {
            // getTables() lists every schema; the application lives in the
            // first one on its search path.
            $schema = trim(explode(',', (string) ($connection->getConfig('search_path') ?: 'public'))[0]);
            $tables = array_filter($tables, static fn (array $table): bool => $table['schema'] === $schema);
        }

        return array_values(array_map(static fn (array $table): string => (string) $table['name'], $tables));
    }

    /**
     * Tables ordered parents before children, plus the columns each table
     * uses to point at its own rows (filled in after the table is copied).
     *
     * @param  list<string>  $tables
     * @return array{0: list<string>, 1: array<string, list<string>>}
     */
    private function dependencyOrder(Connection $target, array $tables): array
    {
        $parents = [];
        $selfReferences = [];

        foreach ($tables as $table) {
            $parents[$table] = [];

            foreach ($target->getSchemaBuilder()->getForeignKeys($table) as $foreignKey) {
                $parent = (string) $foreignKey['foreign_table'];

                if ($parent === $table) {
                    $selfReferences[$table] = [...($selfReferences[$table] ?? []), ...$foreignKey['columns']];
                } elseif (in_array($parent, $tables, true)) {
                    $parents[$table][] = $parent;
                }
            }
        }

        $order = [];

        while ($parents !== []) {
            $ready = array_keys(array_filter($parents, static fn (array $of): bool => array_diff($of, $order) === []));

            if ($ready === []) {
                throw new RuntimeException('Circular foreign keys between: '.implode(', ', array_keys($parents)));
            }

            sort($ready);
            foreach ($ready as $table) {
                $order[] = $table;
                unset($parents[$table]);
            }
        }

        return [$order, $selfReferences];
    }

    /** Open a read-only transaction that sees the source as of one instant. */
    private function beginSnapshot(Connection $source): void
    {
        if ($source->getDriverName() !== 'pgsql') {
            $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        $source->beginTransaction();

        if ($source->getDriverName() === 'pgsql') {
            $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        }
    }

    /**
     * @param  list<string>  $selfReferences  columns pointing at this same table
     * @return int rows copied
     */
    private function copyTable(Connection $source, Connection $target, string $table, array $selfReferences): int
    {
        $sourceColumns = $this->columns($source, $table);
        $targetColumns = $this->columns($target, $table);

        $columns = array_values(array_filter(
            array_keys($sourceColumns),
            static fn (string $column): bool => isset($targetColumns[$column]) && $targetColumns[$column]['generation'] === null
        ));

        if ($selfReferences !== [] && ! in_array('id', $columns, true)) {
            throw new RuntimeException("Table [{$table}] references itself but has no id column.");
        }

        $sourceGrammar = $source->getQueryGrammar();
        $query = $source->table($table)->selectRaw(implode(', ', array_map(
            fn (string $column): string => $this->isGeometry($sourceColumns[$column])
                ? 'ST_AsGeoJSON('.$sourceGrammar->wrap($column).', '.self::GEOJSON_PRECISION.') AS '.$sourceGrammar->wrap($column)
                : $sourceGrammar->wrap($column),
            $columns
        )));

        foreach ($this->primaryKey($source, $table) as $key) {
            $query->orderBy($key);
        }

        $count = 0;
        $batch = [];
        $deferred = [];

        foreach ($query->cursor() as $row) {
            $values = [];

            foreach ($columns as $column) {
                $value = $row->{$column};

                if (in_array($column, $selfReferences, true) && $value !== null) {
                    // Its parent row may come later in the copy; point it
                    // once every row of the table is in.
                    $deferred[$row->id][$column] = $value;
                    $value = null;
                }

                $values[$column] = $this->convert($value, $targetColumns[$column], $target);
            }

            $batch[] = $values;
            $count++;

            if (count($batch) === self::BATCH) {
                $this->insertBatch($target, $table, $columns, $targetColumns, $batch);
                $batch = [];
                $this->progress('copying');
            }
        }

        if ($batch !== []) {
            $this->insertBatch($target, $table, $columns, $targetColumns, $batch);
        }

        foreach ($deferred as $id => $values) {
            $target->table($table)->where('id', $id)->update($values);
        }

        return $count;
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, array<string, mixed>>  $targetColumns
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertBatch(Connection $target, string $table, array $columns, array $targetColumns, array $rows): void
    {
        $grammar = $target->getQueryGrammar();
        $bindings = [];
        $tuples = [];

        foreach ($rows as $row) {
            $placeholders = [];

            foreach ($columns as $column) {
                if (! $this->isGeometry($targetColumns[$column])) {
                    $placeholders[] = '?';
                    $bindings[] = $row[$column];

                    continue;
                }

                if ($target->getDriverName() === 'pgsql') {
                    $placeholders[] = 'ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(CAST(? AS text))), 4326)';
                    $bindings[] = $row[$column];
                } else {
                    // MariaDB's column is MULTIPOLYGON, so a Polygon from
                    // anywhere is wrapped before it is sent.
                    $placeholders[] = 'CASE WHEN ? IS NULL THEN NULL ELSE ST_GeomFromGeoJSON(?) END';
                    $geometry = $row[$column] === null ? null : Spatial::multiPolygonJson((string) $row[$column]);
                    array_push($bindings, $geometry, $geometry);
                }
            }

            $tuples[] = '('.implode(', ', $placeholders).')';
        }

        $target->insert(
            'INSERT INTO '.$grammar->wrapTable($table)
            .' ('.implode(', ', array_map($grammar->wrap(...), $columns)).') VALUES '.implode(', ', $tuples),
            $bindings
        );
    }

    /**
     * A value as the target column will accept it.
     *
     * @param  array<string, mixed>  $column
     */
    private function convert(mixed $value, array $column, Connection $target): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = strtolower((string) $column['type_name']);

        // PostgreSQL returns booleans as PHP bools; MariaDB returns 0/1.
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        // A zone-qualified timestamp has no MariaDB equivalent: store the
        // instant in the application's time zone, as Laravel writes it.
        if ($target->getDriverName() !== 'pgsql' && in_array($type, ['timestamp', 'datetime'], true)
            && is_string($value) && preg_match('/[+-]\d{2}(:?\d{2})?$/', $value)) {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /** @param  list<string>  $tables */
    private function verifyCounts(Connection $source, Connection $target, array $tables): void
    {
        $mismatched = [];

        foreach ($tables as $table) {
            $expected = $source->table($table)->count();
            $actual = $target->table($table)->count();

            if ($expected !== $actual) {
                $mismatched[] = "{$table} ({$expected} → {$actual})";
            }
        }

        if ($mismatched !== []) {
            throw new RuntimeException('Row counts differ after copying: '.implode(', ', $mismatched));
        }
    }

    /**
     * Point each serial sequence past the highest id copied in, so the next
     * insert on this database does not collide with a copied row.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            $idColumn = $this->columns($target, $table)['id'] ?? null;

            if ($idColumn === null || ! $idColumn['auto_increment']) {
                continue;
            }

            $target->statement(
                "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE(MAX(id), 0) + 1, false) FROM ".$target->getQueryGrammar()->wrapTable($table),
                [$table]
            );
        }
    }

    /** @return array<string, array<string, mixed>> keyed by column name */
    private function columns(Connection $connection, string $table): array
    {
        $columns = [];

        foreach ($connection->getSchemaBuilder()->getColumns($table) as $column) {
            $columns[(string) $column['name']] = $column;
        }

        return $columns;
    }

    /** @return list<string> */
    private function primaryKey(Connection $connection, string $table): array
    {
        foreach ($connection->getSchemaBuilder()->getIndexes($table) as $index) {
            if ($index['primary']) {
                return array_values($index['columns']);
            }
        }

        return [];
    }

    /** @param  array<string, mixed>  $column */
    private function isGeometry(array $column): bool
    {
        return in_array(strtolower((string) $column['type_name']), [
            'geometry', 'geography', 'point', 'linestring', 'polygon',
            'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection',
        ], true);
    }

    private function progress(string $step): void
    {
        $this->run['step'] = $step;

        SyncJournal::write($this->run);
    }
}
