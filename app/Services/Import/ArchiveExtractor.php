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
 * so its name passing the path check proves nothing about what would
 * actually be materialised on disk), and both entry count and uncompressed
 * size are capped so a zip bomb cannot fill the disk.
 *
 * The size cap is enforced against bytes actually decompressed and written,
 * never against ZipArchive::statIndex()'s declared "size" — that figure is
 * read straight from the archive's own central directory, so it is exactly
 * as trustworthy as any other attacker-supplied metadata. A crafted archive
 * can declare a tiny size while its real compressed stream inflates to many
 * megabytes; extraction here reads every entry through its own decompression
 * stream in bounded chunks and aborts (deleting whatever this call already
 * wrote) the moment the running total of real bytes exceeds the cap.
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

            /** @var list<string> $names */
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    throw new ArchiveException("Entry #{$i} could not be read.");
                }

                $name = (string) $stat['name'];

                $this->assertSafePath($name);
                $this->assertSafeType($zip, $i, $name);

                $names[$i] = $name;
            }

            if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                throw new ArchiveException("Could not create the destination directory: {$destination}");
            }

            try {
                $this->extractBounded($zip, $names, $destination);
            } catch (ArchiveException $e) {
                // A zip bomb (or any entry whose real decompressed bytes
                // exceed the cap) must not leave a partial extraction
                // behind — the whole point of the cap is to keep the bomb
                // off disk, so whatever this call already wrote before
                // aborting is removed rather than left for the caller to
                // find later.
                $this->removeDirectory($destination);

                throw $e;
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
     * Writes every entry to disk one at a time, reading each entry's own
     * decompression stream in bounded chunks and keeping a running total of
     * bytes actually written — never the entry's declared uncompressed size
     * from ZipArchive::statIndex(), which is attacker-controlled central
     * directory metadata and proved bypassable: a crafted archive can declare
     * a tiny size while its real decompressed stream is many megabytes.
     * Checking the real bytes as they land is the only way this cap can
     * actually bound what reaches the disk.
     *
     * @param  list<string>  $names
     *
     * @throws ArchiveException
     */
    private function extractBounded(ZipArchive $zip, array $names, string $destination): void
    {
        $total = 0;

        foreach ($names as $name) {
            $normalised = str_replace('\\', '/', $name);
            $targetPath = $destination.'/'.$normalised;

            if (str_ends_with($normalised, '/')) {
                if (! is_dir($targetPath) && ! mkdir($targetPath, 0775, true) && ! is_dir($targetPath)) {
                    throw new ArchiveException("Could not create directory for entry: {$name}");
                }

                continue;
            }

            $parent = dirname($targetPath);

            if (! is_dir($parent) && ! mkdir($parent, 0775, true) && ! is_dir($parent)) {
                throw new ArchiveException("Could not create directory for entry: {$name}");
            }

            // getStream() reads by decompressing on the fly, so what comes
            // out of it is the entry's genuine content regardless of what
            // its header claims about size.
            $stream = $zip->getStream($name);

            if ($stream === false) {
                throw new ArchiveException("Entry could not be read: {$name}");
            }

            $out = fopen($targetPath, 'wb');

            if ($out === false) {
                fclose($stream);

                throw new ArchiveException("Could not write entry: {$name}");
            }

            while (! feof($stream)) {
                $chunk = fread($stream, 65536);

                if ($chunk === false) {
                    fclose($stream);
                    fclose($out);

                    throw new ArchiveException("Failed to read entry: {$name}");
                }

                $total += strlen($chunk);

                if ($total > $this->maxTotalBytes) {
                    fclose($stream);
                    fclose($out);

                    throw new ArchiveException('The archive’s uncompressed size exceeds the allowed limit.');
                }

                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    fclose($stream);
                    fclose($out);

                    throw new ArchiveException("Failed to write entry: {$name}");
                }
            }

            fclose($stream);
            fclose($out);
        }
    }

    /** Recursively deletes a partial extraction this call is aborting. */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $fileInfo->isDir() ? @rmdir($fileInfo->getPathname()) : @unlink($fileInfo->getPathname());
        }

        @rmdir($dir);
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
