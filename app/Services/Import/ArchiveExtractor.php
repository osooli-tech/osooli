<?php

declare(strict_types=1);

namespace App\Services\Import;

use ZipArchive;

/**
 * Extracts an untrusted ZIP to a destination directory.
 *
 * Uploads come from the browser, so every entry is treated as hostile:
 * paths are resolved against the destination and rejected if they escape it
 * (zip-slip), entries whose type is neither a regular file nor a directory
 * are rejected (a symlink entry's "content" is a link target, not real data,
 * so its name passing the path check proves nothing about what extractTo()
 * would actually materialise), and both entry count and uncompressed size
 * are capped so a zip bomb cannot fill the disk.
 */
final class ArchiveExtractor
{
    private const TYPE_MASK = 0xF000;

    private const TYPE_REGULAR_FILE = 0x8000;

    private const TYPE_DIRECTORY = 0x4000;

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

        try {
            if ($zip->numFiles > $this->maxEntries) {
                throw new ArchiveException("The archive holds more than {$this->maxEntries} entries.");
            }

            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    throw new ArchiveException("Entry #{$i} could not be read.");
                }

                $total += (int) $stat['size'];

                if ($total > $this->maxTotalBytes) {
                    throw new ArchiveException('The archive’s uncompressed size exceeds the allowed limit.');
                }

                $name = (string) $stat['name'];

                $this->assertSafePath($name);
                $this->assertSafeType($zip, $i, $name);
            }

            if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                throw new ArchiveException("Could not create the destination directory: {$destination}");
            }

            if (! $zip->extractTo($destination)) {
                throw new ArchiveException('The archive could not be extracted.');
            }
        } finally {
            // Every branch above throws before returning, so this always runs
            // whether extraction succeeded or a guard rejected the archive —
            // no path may leave the handle open.
            $zip->close();
        }

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

    /**
     * Rejects an entry whose Unix external attributes mark it as something
     * other than a regular file or a directory — most notably a symlink,
     * whose "content" is a link target rather than real file data. A
     * malicious archive can pair a symlink entry with a following entry
     * that writes through it, none of which shows up as a suspicious path
     * on the symlink entry's own name.
     *
     * Only Unix-origin entries carry meaningful file-type bits in the high
     * 16 bits of their external attributes. An entry written by a DOS or
     * Windows tool reports OPSYS_DOS with no such bits and is never
     * rejected here.
     *
     * @throws ArchiveException
     */
    private function assertSafeType(ZipArchive $zip, int $index, string $name): void
    {
        $opsys = 0;
        $attr = 0;

        if (! $zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $type = ($attr >> 16) & self::TYPE_MASK;

        if ($type !== 0 && $type !== self::TYPE_REGULAR_FILE && $type !== self::TYPE_DIRECTORY) {
            throw new ArchiveException("Archive entry has an unsupported type and cannot be extracted safely: {$name}");
        }
    }
}
