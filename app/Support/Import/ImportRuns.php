<?php

declare(strict_types=1);

namespace App\Support\Import;

use Generator;
use Illuminate\Support\Str;

/**
 * An import on disk, under storage/app/imports/{id}/:
 *
 *  - source.geojson  the uploaded file (unzipped)
 *  - meta.json       state, counts, the decisions taken on the review page
 *  - items.jsonl     one analysed feature per line — what will happen to it
 *  - undo.json       after applying: what was created and what each changed
 *                    row held before, so the import can be reversed
 *
 * Files rather than tables for the same reasons as exports: an import is
 * reviewed over minutes or days, must survive a switch of primary database,
 * and its analysis can run to tens of thousands of lines.
 */
final class ImportRuns
{
    /** Days an applied import can still be undone; its files go after that. */
    public const KEEP_DAYS = 7;

    private const STALE_AFTER_SECONDS = 900;

    public static function root(): string
    {
        $root = storage_path('app/imports');

        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        return $root;
    }

    public static function newId(): string
    {
        return now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
    }

    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^\d{8}-\d{6}-[a-z0-9]{6}$/', $id);
    }

    public static function path(string $id, string $file = ''): string
    {
        $directory = self::root().'/'.$id;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $file === '' ? $directory : $directory.'/'.$file;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        if (! self::isValidId($id) || ! is_file(self::root()."/{$id}/meta.json")) {
            return null;
        }

        $run = json_decode((string) file_get_contents(self::root()."/{$id}/meta.json"), true);

        if (! is_array($run)) {
            return null;
        }

        if (in_array($run['state'] ?? null, ['analysing', 'applying', 'undoing'], true)
            && time() - (int) ($run['heartbeat'] ?? 0) > self::STALE_AFTER_SECONDS) {
            $run['error'] = 'stalled';
            $run['state'] = $run['state'] === 'analysing' ? 'failed' : 'apply_failed';
        }

        return $run;
    }

    /** @param  array<string, mixed>  $run */
    public static function save(array $run): void
    {
        $run['heartbeat'] = time();
        self::writeJson(self::path($run['id'], 'meta.json'), $run);
    }

    /** @param  array<mixed>  $data */
    public static function writeJson(string $path, array $data): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($temporary, $path);
    }

    /** @return array<mixed>|null */
    public static function readJson(string $path): ?array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * The analysed items, one at a time.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public static function items(string $id): Generator
    {
        $path = self::path($id, 'items.jsonl');
        if (! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $item = json_decode($line, true);
                if (is_array($item)) {
                    yield (int) $item['index'] => $item;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return list<array<string, mixed>> newest first */
    public static function recent(?int $userId = null, int $limit = 20): array
    {
        $runs = [];

        foreach (glob(self::root().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $run = self::find(basename($directory));
            if ($run !== null && ($userId === null || (int) ($run['user_id'] ?? 0) === $userId)) {
                $runs[] = $run;
            }
        }

        usort($runs, static fn (array $a, array $b): int => strcmp((string) $b['id'], (string) $a['id']));

        return array_slice($runs, 0, $limit);
    }

    /** Remove imports older than KEEP_DAYS, and with them the chance to undo. */
    public static function prune(): void
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();

        foreach (glob(self::root().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $meta = $directory.'/meta.json';
            if (is_file($meta) && filemtime($meta) < $cutoff) {
                foreach (glob($directory.'/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($directory);
            }
        }
    }
}
