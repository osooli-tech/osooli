<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use JsonException;
use PDO;
use RuntimeException;

/**
 * The database settings managed from the dashboard: how to reach each of the
 * two databases, which of them is primary, and when to sync.
 *
 * They cannot live in a database — the application needs them before it can
 * connect to one — so they are kept in a single file under storage/app,
 * encrypted with APP_KEY because it holds passwords. Nobody edits that file
 * by hand; the dashboard writes it and `db:settings:reset` removes it.
 *
 * When the file is missing or cannot be decrypted (APP_KEY changed, file
 * damaged) the application falls back to the connection in .env, so a bad
 * save can never lock anyone out of the dashboard that would repair it.
 */
final class DatabaseSettings
{
    /** The two databases the application can run on, by connection name. */
    public const CONNECTIONS = ['pgsql', 'mariadb'];

    /** Fields each connection form holds; the password is handled apart. */
    public const FIELDS = [
        'pgsql' => ['host', 'port', 'database', 'username', 'sslmode'],
        'mariadb' => ['host', 'port', 'database', 'username'],
    ];

    /** Seconds to wait for a database server to accept a connection. */
    public const CONNECT_TIMEOUT = 5;

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    private bool $unreadable = false;

    /** database.default as .env set it, captured before apply() changes it. */
    private readonly string $envDefault;

    public function __construct(private readonly Config $config)
    {
        $default = (string) $config->get('database.default');
        $this->envDefault = in_array($default, self::CONNECTIONS, true) ? $default : 'pgsql';
    }

    public static function path(): string
    {
        return (string) config('database.settings_file', storage_path('app/database-settings.enc'));
    }

    /**
     * Point the configured connections at the stored settings and make the
     * chosen one the default. Called once per request, before anything
     * touches the database.
     */
    public function apply(): void
    {
        foreach (self::CONNECTIONS as $name) {
            $stored = $this->connection($name);

            if ($stored === null) {
                continue;
            }

            $this->config->set("database.connections.{$name}", self::merge(
                (array) $this->config->get("database.connections.{$name}", []),
                $stored
            ));
        }

        $primary = $this->all()['primary'] ?? null;

        if (is_string($primary) && $this->isConfigured($primary)) {
            $this->config->set('database.default', $primary);
        }
    }

    /**
     * A connection's config array with stored values laid over it. `url` is
     * cleared: when .env sets DB_URL it would otherwise win over every field.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $stored): array
    {
        $merged = array_merge($base, array_filter(
            $stored,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        ));
        $merged['url'] = null;
        $merged['password'] = (string) ($stored['password'] ?? '');

        // Give up on an unreachable server after a few seconds instead of
        // the driver's default, so a dead secondary cannot stall the
        // settings page (or a request) for half a minute or more.
        $merged['options'] = (array) ($merged['options'] ?? []) + [PDO::ATTR_TIMEOUT => self::CONNECT_TIMEOUT];

        return $merged;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->settings ??= $this->read();
    }

    /** Whether settings were saved from the dashboard (and could be read). */
    public function isStored(): bool
    {
        return is_file(self::path()) && ! $this->unreadableAfterRead();
    }

    /** True when the file exists but could not be decrypted or parsed. */
    public function unreadableAfterRead(): bool
    {
        $this->all();

        return $this->unreadable;
    }

    /** The stored connection details, password included, or null. */
    public function connection(string $name): ?array
    {
        $connection = $this->all()['connections'][$name] ?? null;

        return is_array($connection) ? $connection : null;
    }

    /**
     * Whether the application knows how to reach this database: saved from
     * the dashboard, or — before anything is saved — the connection .env
     * already uses.
     */
    public function isConfigured(string $name): bool
    {
        return $this->connection($name) !== null
            || $name === $this->envDefault();
    }

    public function primary(): string
    {
        $primary = $this->all()['primary'] ?? null;

        return is_string($primary) && $this->isConfigured($primary) ? $primary : $this->envDefault();
    }

    /** The other of the two databases. */
    public function secondary(): string
    {
        return $this->primary() === 'pgsql' ? 'mariadb' : 'pgsql';
    }

    /** @return array{auto: bool, time: string, include_sessions: bool} */
    public function sync(): array
    {
        $sync = (array) ($this->all()['sync'] ?? []);

        return [
            'auto' => (bool) ($sync['auto'] ?? false),
            'time' => is_string($sync['time'] ?? null) ? $sync['time'] : '03:00',
            'include_sessions' => (bool) ($sync['include_sessions'] ?? true),
        ];
    }

    /**
     * Store one connection's details. A null password keeps the stored one —
     * the form never shows a saved password, so an empty field means
     * "unchanged", not "no password".
     *
     * @param  array<string, mixed>  $values
     */
    public function saveConnection(string $name, array $values, ?string $password): void
    {
        $this->assertKnown($name);

        $settings = $this->all();
        $current = $this->connection($name) ?? [];

        $connection = [];
        foreach (self::FIELDS[$name] as $field) {
            $connection[$field] = isset($values[$field]) ? trim((string) $values[$field]) : null;
        }
        $connection['password'] = $password ?? ($current['password'] ?? '');

        $settings['connections'][$name] = $connection;

        $this->write($settings);
    }

    public function setPrimary(string $name): void
    {
        $this->assertKnown($name);

        if (! $this->isConfigured($name)) {
            throw new RuntimeException("Connection [{$name}] is not configured.");
        }

        $settings = $this->all();
        $settings['primary'] = $name;

        // The connection .env uses becomes an explicit entry the first time
        // anything is saved, so switching away and back never depends on
        // .env still holding the same values.
        foreach (self::CONNECTIONS as $connection) {
            if (! isset($settings['connections'][$connection]) && $connection === $this->envDefault()) {
                $settings['connections'][$connection] = $this->fromConfig($connection);
            }
        }

        $this->write($settings);
    }

    /** @param  array{auto: bool, time: string, include_sessions: bool}  $sync */
    public function saveSync(array $sync): void
    {
        $settings = $this->all();
        $settings['sync'] = $sync;

        $this->write($settings);
    }

    /** Forget everything saved from the dashboard and go back to .env. */
    public function reset(): void
    {
        if (is_file(self::path())) {
            unlink(self::path());
        }

        $this->settings = null;
        $this->unreadable = false;
    }

    /** The default connection named in .env, before any stored settings. */
    public function envDefault(): string
    {
        return $this->envDefault;
    }

    /** @return array<string, mixed> */
    private function fromConfig(string $name): array
    {
        $config = (array) $this->config->get("database.connections.{$name}", []);

        $connection = [];
        foreach (self::FIELDS[$name] as $field) {
            $connection[$field] = isset($config[$field]) ? (string) $config[$field] : null;
        }
        $connection['password'] = (string) ($config['password'] ?? '');

        return $connection;
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $this->unreadable = false;

        if (! is_file(self::path())) {
            return [];
        }

        try {
            $decoded = json_decode(
                (string) $this->encrypter()->decryptString((string) file_get_contents(self::path())),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            return is_array($decoded) ? $decoded : [];
        } catch (DecryptException|JsonException $e) {
            // Falls back to .env rather than failing every request.
            $this->unreadable = true;
            Log::warning('Database settings file could not be read; using .env.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** @param  array<string, mixed>  $settings */
    private function write(array $settings): void
    {
        $settings['updated_at'] = now()->toIso8601String();

        $directory = dirname(self::path());
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Write-then-rename, so a request never reads a half-written file.
        $temporary = self::path().'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, $this->encrypter()->encryptString((string) json_encode($settings)), LOCK_EX);
        rename($temporary, self::path());

        $this->settings = $settings;
        $this->unreadable = false;
    }

    /**
     * Resolved only when the file is actually read or written: building the
     * encrypter fails without an APP_KEY, and `key:generate` must still run.
     */
    private function encrypter(): Encrypter
    {
        return app('encrypter');
    }

    private function assertKnown(string $name): void
    {
        if (! in_array($name, self::CONNECTIONS, true)) {
            throw new RuntimeException("Unknown connection [{$name}].");
        }
    }
}
