<?php

declare(strict_types=1);

namespace App\Services\Import;

use ZipArchive;

/**
 * Extracts an untrusted ZIP to a destination directory.
 *
 * Uploads come from the browser, so every entry is treated as hostile:
 * paths are resolved against the destination and rejected if they escape it
 * (zip-slip), and both entry count and uncompressed size are capped so a zip
 * bomb cannot fill the disk.
 */
final class ArchiveExtractor
{
    public function __construct(
        private readonly int $maxEntries = 5000,
        private readonly int $maxTotalBytes = 2_147_483_648,
    ) {}

    /** @throws ArchiveException */
    public function extract(string $zipPath, string $destination): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new ArchiveException('The file could not be opened as a ZIP archive.');
        }

        if ($zip->numFiles > $this->maxEntries) {
            $zip->close();
            throw new ArchiveException("The archive holds more than {$this->maxEntries} entries.");
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                $zip->close();
                throw new ArchiveException("Entry #{$i} could not be read.");
            }

            $total += (int) $stat['size'];

            if ($total > $this->maxTotalBytes) {
                $zip->close();
                throw new ArchiveException('The archive’s uncompressed size exceeds the allowed limit.');
            }

            $this->assertSafePath((string) $stat['name']);
        }

        if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
            $zip->close();
            throw new ArchiveException("Could not create the destination directory: {$destination}");
        }

        if (! $zip->extractTo($destination)) {
            $zip->close();
            throw new ArchiveException('The archive could not be extracted.');
        }

        $zip->close();

        return $destination;
    }

    /**
     * Rejects absolute paths and any path that climbs out of the destination.
     * Checked on the entry name before extraction — realpath() is useless here
     * because the file does not exist yet.
     *
     * @throws ArchiveException
     */
    private function assertSafePath(string $name): void
    {
        $normalised = str_replace('\\', '/', $name);

        if (str_starts_with($normalised, '/') || preg_match('/^[A-Za-z]:/', $normalised) === 1) {
            throw new ArchiveException("Archive entry uses an absolute path and would land outside the destination: {$name}");
        }

        $depth = 0;
        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $depth += $segment === '..' ? -1 : 1;

            if ($depth < 0) {
                throw new ArchiveException("Archive entry would be written outside the destination: {$name}");
            }
        }
    }
}
