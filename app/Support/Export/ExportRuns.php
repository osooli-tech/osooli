<?php

declare(strict_types=1);

namespace App\Support\Export;

use Illuminate\Support\Str;

/**
 * Bulk exports on disk: each run is a GeoJSON file plus a small JSON record
 * of who asked for it, with which filters, how far it got and how it ended.
 *
 * Kept as files under storage/app/exports rather than in a table, so a run
 * survives switching the primary database and a large export never touches
 * a table while it writes. Files are removed after KEEP_DAYS.
 */
final class ExportRuns
{
    /** Days an export stays downloadable. */
    public const KEEP_DAYS = 7;

    /** A run with no progress for this long is taken to have died. */
    private const STALE_AFTER_SECONDS = 900;

    public static function directory(): string
    {
        $directory = storage_path('app/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public static function newId(): string
    {
        return now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^\d{8}-\d{6}-[a-z0-9]{6}$/', $id);
    }

    public static function filePath(string $id): string
    {
        return self::directory()."/{$id}.geojson";
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        if (! self::isValidId($id)) {
            return null;
        }

        $path = self::directory()."/{$id}.json";
        $run = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($run)) {
            return null;
        }

        if (($run['state'] ?? null) === 'running' && time() - (int) ($run['heartbeat'] ?? 0) > self::STALE_AFTER_SECONDS) {
            $run['state'] = 'failed';
            $run['error'] = 'stalled';
        }

        return $run;
    }

    /** @param  array<string, mixed>  $run */
    public static function save(array $run): void
    {
        $run['heartbeat'] = time();
        $path = self::directory()."/{$run['id']}.json";
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        file_put_contents($temporary, (string) json_encode($run, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        rename($temporary, $path);
    }

    /**
     * Recent runs, newest first. `$userId` limits the list to one person's
     * own exports; null lists everyone's.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(?int $userId = null, int $limit = 20): array
    {
        $runs = [];

        foreach (glob(self::directory().'/*.json') ?: [] as $file) {
            $run = self::find(basename($file, '.json'));

            if ($run !== null && ($userId === null || (int) ($run['user_id'] ?? 0) === $userId)) {
                $runs[] = $run;
            }
        }

        usort($runs, static fn (array $a, array $b): int => strcmp((string) $b['id'], (string) $a['id']));

        return array_slice($runs, 0, $limit);
    }

    /** "3.4 MB" — without the intl extension Number::fileSize() needs. */
    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $bytes : number_format($size, 1)).' '.$units[$unit];
    }

    /** Delete runs and their files older than KEEP_DAYS. */
    public static function prune(): int
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();
        $removed = 0;

        foreach (glob(self::directory().'/*.json') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                $id = basename($file, '.json');
                @unlink(self::filePath($id));
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }
}
