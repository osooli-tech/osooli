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
