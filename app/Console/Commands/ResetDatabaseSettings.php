<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\DatabaseSettings;
use Illuminate\Console\Command;

/**
 * The way back when the dashboard's database settings leave the site unable
 * to reach its database: forget them, and run on the connection in .env.
 */
class ResetDatabaseSettings extends Command
{
    protected $signature = 'db:settings:reset {--force : Do not ask for confirmation}';

    protected $description = 'Delete the database settings saved from the dashboard and fall back to .env';

    public function handle(DatabaseSettings $settings): int
    {
        if (! is_file(DatabaseSettings::path())) {
            $this->info('No saved database settings; the application already uses .env.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete the saved database settings (connections, primary, sync schedule)?')) {
            return self::FAILURE;
        }

        $settings->reset();
        $this->info('Database settings removed. The application now uses the connection in .env ('.$settings->envDefault().').');

        return self::SUCCESS;
    }
}
