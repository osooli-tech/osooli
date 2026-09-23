<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Where a sync reports its progress and leaves its result.
 *
 * Kept in files next to the settings, not in a table: a sync rewrites one
 * database from the other, and the record of that must not be one of the
 * rows being rewritten — nor vanish when the primary is switched.
 */
final class SyncJournal
{
    /** How many past runs the history keeps. */
    private const HISTORY_LENGTH = 30;

    /** A run with no progress for this long is taken to have died. */
    private const STALE_AFTER_SECONDS = 900;

    private static function directory(): string
    {
        $directory = storage_path('app/database-sync');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    /**
     * The current or most recent run.
     *
     * @return array<string, mixed>|null
     */
    public static function status(): ?array
    {
        $status = self::readJson('status.json');

        if ($status !== null && ($status['state'] ?? null) === 'running'
            && time() - (int) ($status['heartbeat'] ?? 0) > self::STALE_AFTER_SECONDS) {
            $status['state'] = 'failed';
            $status['error'] = 'stalled';
        }

        return $status;
    }

    public static function isRunning(): bool
    {
        return (self::status()['state'] ?? null) === 'running';
    }

    /** @param  array<string, mixed>  $status */
    public static function write(array $status): void
    {
        $status['heartbeat'] = time();

        self::writeJson('status.json', $status);
    }

    /** @param  array<string, mixed>  $run */
    public static function finish(array $run): void
    {
        self::write($run);

        $history = self::history();
        array_unshift($history, $run);

        self::writeJson('history.json', array_slice($history, 0, self::HISTORY_LENGTH));
    }

    /** @return list<array<string, mixed>> */
    public static function history(): array
    {
        return array_values(self::readJson('history.json') ?? []);
    }

    /**
     * An exclusive lock for the length of a run, or null when another run
     * holds it. Released when the handle is closed or the process ends.
     *
     * @return resource|null
     */
    public static function lock()
    {
        $handle = fopen(self::directory().'/sync.lock', 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            return null;
        }

        return $handle;
    }

    /** @return array<mixed>|null */
    private static function readJson(string $file): ?array
    {
        $path = self::directory().'/'.$file;

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param  array<mixed>  $data */
    private static function writeJson(string $file, array $data): void
    {
        $path = self::directory().'/'.$file;
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        file_put_contents($temporary, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        rename($temporary, $path);
    }
}
