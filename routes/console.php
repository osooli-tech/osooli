<?php

declare(strict_types=1);

use App\Support\Database\DatabaseSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly (or whenever the settings say) copy of the primary database into
// the secondary one. Turned on and timed from the database settings page;
// needs the server's cron to run `php artisan schedule:run` every minute.
Schedule::command('db:sync --scheduled')
    ->dailyAt(app(DatabaseSettings::class)->sync()['time'])
    ->when(fn (): bool => app(DatabaseSettings::class)->sync()['auto']);

// withoutOverlapping() matters here specifically: this command recursively
// deletes directories — a second invocation starting while the first is still
// running (a slow disk, a huge backlog) must not run the same delete logic
// concurrently over the same rows. onOneServer() is safe to add too: the default cache store is
// 'database' (config/cache.php), whose driver implements Laravel's
// LockProvider contract against the cache_locks table created in
// 0001_01_01_000001_create_cache_table.php, so the mutex it needs has
// somewhere real to live.
Schedule::command('app:prune-import-batches')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
