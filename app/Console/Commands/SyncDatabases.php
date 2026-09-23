<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\Database\DatabaseSettings;
use App\Support\Database\DatabaseSynchronizer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Copies the primary database into the secondary one — or, with --from and
 * --to, in whichever direction is asked for, to restore from the copy.
 */
class SyncDatabases extends Command
{
    protected $signature = 'db:sync
                            {--from= : Source connection (default: the primary database)}
                            {--to= : Target connection (default: the secondary database)}
                            {--fresh : Drop and rebuild every table in the target before copying}
                            {--scheduled : Run as the scheduled sync (skipped when auto-sync is off)}
                            {--force : Do not ask before overwriting the primary database}';

    protected $description = 'Mirror one database into the other (PostgreSQL ⇄ MariaDB)';

    public function handle(DatabaseSettings $settings, DatabaseSynchronizer $synchronizer): int
    {
        if ($this->option('scheduled') && ! $settings->sync()['auto']) {
            $this->info('Automatic sync is turned off in the database settings.');

            return self::SUCCESS;
        }

        $from = (string) ($this->option('from') ?: $settings->primary());
        $to = (string) ($this->option('to') ?: ($from === $settings->primary() ? $settings->secondary() : $settings->primary()));

        foreach ([$from, $to] as $name) {
            if (! $settings->isConfigured($name)) {
                $this->error("The [{$name}] database is not configured. Add it on the database settings page first.");

                return self::FAILURE;
            }
        }

        if ($to === $settings->primary() && ! $this->option('force')
            && ! $this->confirm("[{$to}] is the PRIMARY database. Every table in it will be replaced with the contents of [{$from}]. Continue?")) {
            return self::FAILURE;
        }

        $this->info("Syncing {$from} → {$to}".($this->option('fresh') ? ' (rebuilding tables)' : '').' …');

        try {
            $run = $synchronizer->run($from, $to, (bool) $this->option('fresh'), $this->option('scheduled') ? 'scheduled' : 'console');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        AuditLog::create([
            'user_id' => null,
            'action' => $run['state'] === 'succeeded' ? 'database.sync' : 'database.sync_failed',
            'target_type' => 'database',
        ]);

        if ($run['state'] !== 'succeeded') {
            $this->error('Sync failed: '.$run['error']);

            return self::FAILURE;
        }

        $this->table(['Table', 'Rows'], collect($run['tables'])->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        $this->info("Done: {$run['rows']} rows in ".count($run['tables']).' tables; schema '.$run['schema'].'.');

        return self::SUCCESS;
    }
}
