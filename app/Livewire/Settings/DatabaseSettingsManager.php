<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use App\Support\Database\ConnectionProbe;
use App\Support\Database\DatabaseSettings;
use App\Support\Database\DatabaseSynchronizer;
use App\Support\Database\SyncJournal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * The database settings page: how to reach the PostgreSQL and MariaDB
 * databases, which of them the application runs on, and syncing one into
 * the other.
 *
 * Nothing here is saved without first proving it works: connection details
 * are tested before they are stored, and a database only becomes primary
 * once it is reachable and its schema is complete.
 */
class DatabaseSettingsManager extends Component
{
    /**
     * Connection forms, keyed by connection name. The password field is
     * always empty on load — a saved password is never sent to the browser.
     *
     * @var array<string, array<string, string|null>>
     */
    public array $forms = [];

    /** @var array<string, array<string, mixed>> health of each database, refreshed on demand */
    public array $statuses = [];

    /** @var array<string, array{ok: bool, version: string|null, error: string|null}> last connection test per form */
    public array $tests = [];

    public bool $autoSync = false;

    public string $syncTime = '03:00';

    public bool $includeSessions = true;

    public bool $fresh = false;

    /** Whether the last poll saw a sync running — so its end can refresh the status cards. */
    public bool $wasRunning = false;

    public function mount(DatabaseSettings $settings): void
    {
        $this->authorize('database.manage');

        foreach (DatabaseSettings::CONNECTIONS as $name) {
            $stored = $settings->connection($name) ?? $this->fromConfig($name, $settings);

            $form = [];
            foreach (DatabaseSettings::FIELDS[$name] as $field) {
                $form[$field] = isset($stored[$field]) ? (string) $stored[$field] : '';
            }
            $form['password'] = '';

            $this->forms[$name] = $form;
        }

        $sync = $settings->sync();
        $this->autoSync = $sync['auto'];
        $this->syncTime = $sync['time'];
        $this->includeSessions = $sync['include_sessions'];

        $this->wasRunning = SyncJournal::isRunning();
        $this->refreshStatuses();
    }

    /** Called by the page every few seconds while a sync runs. */
    public function poll(): void
    {
        $running = SyncJournal::isRunning();

        if ($this->wasRunning && ! $running) {
            $this->refreshStatuses();
        }

        $this->wasRunning = $running;
    }

    public function refreshStatuses(): void
    {
        $settings = app(DatabaseSettings::class);
        $probe = app(ConnectionProbe::class);

        foreach (DatabaseSettings::CONNECTIONS as $name) {
            $this->statuses[$name] = $settings->isConfigured($name)
                ? $probe->status($name)
                : ['ok' => false, 'version' => null, 'error' => null, 'migrated' => false, 'pending' => 0, 'counts' => [], 'unconfigured' => true];
        }
    }

    public function test(string $name): void
    {
        $this->authorize('database.manage');
        $this->validateConnection($name);

        $this->tests[$name] = app(ConnectionProbe::class)->trial($name, $this->detailsFor($name));
    }

    public function saveConnection(string $name): void
    {
        $this->authorize('database.manage');
        $this->validateConnection($name);

        $settings = app(DatabaseSettings::class);
        $result = app(ConnectionProbe::class)->trial($name, $this->detailsFor($name));
        $this->tests[$name] = $result;

        if (! $result['ok']) {
            $this->dispatch('toast', type: 'error', message: __('database_settings.save_needs_connection'));

            return;
        }

        $password = $this->forms[$name]['password'] === '' ? null : (string) $this->forms[$name]['password'];
        $settings->saveConnection($name, $this->forms[$name], $password);
        $this->forms[$name]['password'] = '';

        $this->audit('database.settings_update');

        // The next request applies the new details; this one refreshes its
        // own view of the connection so the status card is current.
        config(['database.connections.'.$name => DatabaseSettings::merge(
            (array) config("database.connections.{$name}", []),
            (array) $settings->connection($name)
        )]);
        $this->refreshStatuses();

        $this->dispatch('toast', type: 'success', message: __('database_settings.connection_saved'));
    }

    public function saveSync(): void
    {
        $this->authorize('database.manage');

        $this->validate([
            'autoSync' => ['boolean'],
            'syncTime' => ['required', 'date_format:H:i'],
            'includeSessions' => ['boolean'],
        ], [], [
            'syncTime' => __('database_settings.sync_time'),
        ]);

        app(DatabaseSettings::class)->saveSync([
            'auto' => $this->autoSync,
            'time' => $this->syncTime,
            'include_sessions' => $this->includeSessions,
        ]);

        $this->audit('database.sync_settings_update');
        $this->dispatch('toast', type: 'success', message: __('database_settings.sync_saved'));
    }

    /**
     * Make `$name` the primary database straight away. Refused unless it is
     * reachable and fully migrated — and, when it holds no parcels while the
     * current primary does, refused too: that is almost certainly a switch
     * made before syncing, and it would present an empty system.
     */
    public function switchPrimary(string $name): void
    {
        $this->authorize('database.manage');

        $settings = app(DatabaseSettings::class);

        if ($name === $settings->primary() || ! in_array($name, DatabaseSettings::CONNECTIONS, true)) {
            return;
        }

        $this->refreshStatuses();
        $status = $this->statuses[$name];

        if (! $status['ok'] || ! $status['migrated']) {
            $this->dispatch('toast', type: 'error', message: __('database_settings.switch_needs_schema'));

            return;
        }

        $current = $this->statuses[$settings->primary()]['counts']['parcels'] ?? 0;
        if ((int) ($status['counts']['parcels'] ?? 0) === 0 && (int) $current > 0) {
            $this->dispatch('toast', type: 'error', message: __('database_settings.switch_target_empty'));

            return;
        }

        self::makePrimary($name, Auth::id());

        $this->dispatch('toast', type: 'success', message: __('database_settings.switched', ['name' => __('database_settings.names.'.$name)]));
        $this->redirectRoute('settings.database');
    }

    /** Copy the primary into the secondary in the background, then optionally make the copy primary. */
    public function startSync(bool $thenSwitch = false): void
    {
        $this->authorize('database.manage');

        $settings = app(DatabaseSettings::class);
        $from = $settings->primary();
        $to = $settings->secondary();

        if (! $settings->isConfigured($to)) {
            $this->dispatch('toast', type: 'error', message: __('database_settings.secondary_unconfigured'));

            return;
        }

        if (SyncJournal::isRunning()) {
            $this->dispatch('toast', type: 'error', message: __('database_settings.sync_already_running'));

            return;
        }

        $fresh = $this->fresh;
        $userId = Auth::id();

        // Shown at once; the run overwrites it as soon as it starts.
        SyncJournal::write([
            'state' => 'running', 'step' => 'queued', 'from' => $from, 'to' => $to,
            'fresh' => $fresh, 'started_at' => now()->toIso8601String(), 'done' => 0, 'total' => 0,
            'rows' => 0, 'table' => null, 'tables' => [], 'error' => null,
        ]);

        // After the response is sent, so the page is not held open for the
        // length of the copy; the page polls the journal for progress.
        \Illuminate\Support\defer(static function () use ($from, $to, $fresh, $userId, $thenSwitch): void {
            try {
                $run = app(DatabaseSynchronizer::class)->run($from, $to, $fresh, 'dashboard', $userId);
            } catch (Throwable $e) {
                report($e);

                return;
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => $run['state'] === 'succeeded' ? 'database.sync' : 'database.sync_failed',
                'target_type' => 'database',
            ]);

            if ($thenSwitch && $run['state'] === 'succeeded') {
                self::makePrimary($to, $userId);
            }
        });

        $this->fresh = false;
        $this->wasRunning = true;
        $this->dispatch('toast', type: 'success', message: __('database_settings.sync_started'));
    }

    /**
     * Switch the primary and record it — in the new primary's audit log,
     * since that is the one people will be reading from now on.
     */
    private static function makePrimary(string $name, ?int $userId): void
    {
        app(DatabaseSettings::class)->setPrimary($name);

        try {
            // Whatever the new primary cached when it last served — the
            // permission cache above all — predates everything since.
            foreach (['cache', 'cache_locks'] as $table) {
                if (DB::connection($name)->getSchemaBuilder()->hasTable($table)) {
                    DB::connection($name)->table($table)->delete();
                }
            }

            AuditLog::on($name)->create([
                'user_id' => $userId,
                'action' => 'database.switch_primary',
                'target_type' => 'database',
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function render(): View
    {
        $settings = app(DatabaseSettings::class);
        $status = SyncJournal::status();

        return view('livewire.settings.database-settings-manager', [
            'primary' => $settings->primary(),
            'secondary' => $settings->secondary(),
            'configured' => array_combine(
                DatabaseSettings::CONNECTIONS,
                array_map($settings->isConfigured(...), DatabaseSettings::CONNECTIONS)
            ),
            'storedPassword' => array_combine(
                DatabaseSettings::CONNECTIONS,
                array_map(fn (string $name): bool => ($settings->connection($name)['password'] ?? '') !== '', DatabaseSettings::CONNECTIONS)
            ),
            'unreadable' => $settings->unreadableAfterRead(),
            'run' => $status,
            'running' => ($status['state'] ?? null) === 'running',
            'history' => array_slice(SyncJournal::history(), 0, 10),
        ]);
    }

    private function validateConnection(string $name): void
    {
        abort_unless(in_array($name, DatabaseSettings::CONNECTIONS, true), 404);

        $rules = [
            "forms.{$name}.host" => ['required', 'string', 'max:255'],
            "forms.{$name}.port" => ['required', 'integer', 'between:1,65535'],
            "forms.{$name}.database" => ['required', 'string', 'max:128'],
            "forms.{$name}.username" => ['required', 'string', 'max:128'],
            "forms.{$name}.password" => ['nullable', 'string', 'max:255'],
        ];

        if ($name === 'pgsql') {
            $rules["forms.{$name}.sslmode"] = ['required', Rule::in(['disable', 'prefer', 'require', 'verify-ca', 'verify-full'])];
        }

        $this->validate($rules, [], [
            "forms.{$name}.host" => __('database_settings.host'),
            "forms.{$name}.port" => __('database_settings.port'),
            "forms.{$name}.database" => __('database_settings.database'),
            "forms.{$name}.username" => __('database_settings.username'),
            "forms.{$name}.password" => __('database_settings.password'),
            "forms.{$name}.sslmode" => __('database_settings.sslmode'),
        ]);
    }

    /**
     * The form's values ready to connect with; an empty password means the
     * one already saved.
     *
     * @return array<string, mixed>
     */
    private function detailsFor(string $name): array
    {
        $details = $this->forms[$name];
        $details['password'] = $details['password'] !== ''
            ? $details['password']
            : (app(DatabaseSettings::class)->connection($name)['password'] ?? (string) config("database.connections.{$name}.password", ''));

        return $details;
    }

    /**
     * Before anything is saved, the form starts from what the application
     * is already using — but only for the connection .env really configures.
     *
     * @return array<string, mixed>
     */
    private function fromConfig(string $name, DatabaseSettings $settings): array
    {
        if ($name !== $settings->envDefault()) {
            return $name === 'pgsql' ? ['port' => '5432', 'sslmode' => 'require'] : ['port' => '3306'];
        }

        return (array) config("database.connections.{$name}", []);
    }

    private function audit(string $action): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'target_type' => 'database',
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
