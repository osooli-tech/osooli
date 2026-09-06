<?php

declare(strict_types=1);

namespace App\Services\Import;

use Symfony\Component\Process\Process;

/**
 * Turns an uploaded geodatabase into GeoJSON in EPSG:4326.
 *
 * PHP cannot read an ESRI File Geodatabase and no library exists, so this
 * shells out to GDAL's ogr2ogr. A plain .geojson upload is passed straight
 * through, which is also the escape hatch when the host has no GDAL.
 *
 * convert() is idempotent per $workDir: GdbImporter::analyze() and commit()
 * both call it with the same ($sourcePath, $workDir) pair for one batch, and
 * a second call for a $workDir that already holds a converted output reuses
 * it rather than re-extracting and re-running ogr2ogr — see the cache check
 * at the top of convert().
 */
final class GdbConverter
{
    public function __construct(
        private readonly ArchiveExtractor $extractor,
        private readonly GdbLayerPicker $layerPicker = new GdbLayerPicker,
    ) {}

    public function isAvailable(): bool
    {
        $process = new Process([$this->binary(), '--version']);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @throws ArchiveException
     */
    public function convert(string $sourcePath, string $workDir): string
    {
        if (str_ends_with(strtolower($sourcePath), '.geojson') || str_ends_with(strtolower($sourcePath), '.json')) {
            return $sourcePath;
        }

        $output = $workDir.'/converted.geojson';

        // GdbImporter::analyze() and commit() both call convert() with the
        // same ($sourcePath, $workDir) pair for one batch. Without this
        // cache, commit() used to silently re-extract (up to the 2 GB cap)
        // and re-run a 600s-timeout ogr2ogr a second time, inside the commit
        // job's own budget, producing a fresh re-derivation of the GeoJSON
        // instead of committing the exact file the operator's preview
        // described — see I4 in the final review. Once a conversion has
        // produced a non-empty output for this $workDir, every later call
        // for the same $workDir reuses it unchanged.
        if (is_file($output) && filesize($output) > 0) {
            return $output;
        }

        // Checked before extraction: on a host without GDAL there is no point
        // spending time and disk unpacking what may be a very large archive
        // only to fail afterwards. The .geojson/.json passthrough above must
        // stay ahead of this check — it needs no GDAL at all and is the
        // documented escape hatch for GDAL-less hosts.
        if (! $this->isAvailable()) {
            throw new ArchiveException(
                'The ogr2ogr binary (GDAL) was not found, so a geodatabase cannot be converted. '
                .'Install GDAL, set IMPORT_OGR2OGR_PATH to its full path, or upload a GeoJSON export instead.'
            );
        }

        $extractDir = $workDir.'/extracted';

        // Fresh per conversion attempt, the same reasoning as
        // DocumentImporter::inspect(): a leftover extraction from an earlier,
        // failed attempt at this same $workDir must not silently reappear or
        // mix with this run's entries.
        $this->removeDirectory($extractDir);

        $extracted = $this->extractor->extract($sourcePath, $extractDir);
        $gdb = $this->findGeodatabase($extracted);

        if ($gdb === null) {
            throw new ArchiveException('No .gdb directory was found inside the archive.');
        }

        $layer = $this->layerPicker->pick($gdb);

        $process = new Process([
            // "--" stops ogr2ogr from parsing a layer name starting with "-" as an option.
            // -overwrite makes a stale $output from an earlier, incomplete
            // attempt at this $workDir irrelevant rather than version-dependent
            // GDAL behaviour (truncate vs. refuse) deciding what happens to it.
            $this->binary(), '-f', 'GeoJSON', '-t_srs', 'EPSG:4326', '-overwrite', $output, $gdb, '--', $layer,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($output)) {
            throw new ArchiveException('ogr2ogr failed to convert the geodatabase: '.trim($process->getErrorOutput()));
        }

        // The extracted geodatabase is only scratch space for this
        // conversion — once converted.geojson exists it is the only file
        // GdbImporter needs from $workDir, so the extracted copy (which can
        // be large, up to the 2 GB archive cap) is removed rather than left
        // on disk for the lifetime of the batch.
        $this->removeDirectory($extracted);

        return $output;
    }

    private function binary(): string
    {
        return (string) config('imports.ogr2ogr_path', 'ogr2ogr');
    }

    /** Recursively deletes a directory this converter used as scratch space. */
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

    private function findGeodatabase(string $root): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && str_ends_with(strtolower($entry->getFilename()), '.gdb')) {
                return $entry->getPathname();
            }
        }

        return null;
    }
}
