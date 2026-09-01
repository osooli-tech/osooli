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

    /** @throws ArchiveException */
    public function convert(string $sourcePath, string $workDir): string
    {
        if (str_ends_with(strtolower($sourcePath), '.geojson') || str_ends_with(strtolower($sourcePath), '.json')) {
            return $sourcePath;
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

        $extracted = $this->extractor->extract($sourcePath, $workDir.'/extracted');
        $gdb = $this->findGeodatabase($extracted);

        if ($gdb === null) {
            throw new ArchiveException('No .gdb directory was found inside the archive.');
        }

        $layer = $this->layerPicker->pick($gdb);
        $output = $workDir.'/converted.geojson';

        $process = new Process([
            // "--" stops ogr2ogr from parsing a layer name starting with "-" as an option.
            $this->binary(), '-f', 'GeoJSON', '-t_srs', 'EPSG:4326', $output, $gdb, '--', $layer,
        ]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($output)) {
            throw new ArchiveException('ogr2ogr failed to convert the geodatabase: '.trim($process->getErrorOutput()));
        }

        return $output;
    }

    private function binary(): string
    {
        return (string) config('imports.ogr2ogr_path', 'ogr2ogr');
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
