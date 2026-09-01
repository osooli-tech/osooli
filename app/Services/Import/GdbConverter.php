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
    private const REQUIRED_FIELDS = ['Geo_ID', 'Deed_No'];

    public function __construct(private readonly ArchiveExtractor $extractor) {}

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

        $extracted = $this->extractor->extract($sourcePath, $workDir.'/extracted');
        $gdb = $this->findGeodatabase($extracted);

        if ($gdb === null) {
            throw new ArchiveException('No .gdb directory was found inside the archive.');
        }

        if (! $this->isAvailable()) {
            throw new ArchiveException(
                'The ogr2ogr binary (GDAL) was not found, so a geodatabase cannot be converted. '
                .'Install GDAL, set IMPORT_OGR2OGR_PATH to its full path, or upload a GeoJSON export instead.'
            );
        }

        $layer = $this->pickLayer($gdb);
        $output = $workDir.'/converted.geojson';

        $process = new Process([
            $this->binary(), '-f', 'GeoJSON', '-t_srs', 'EPSG:4326', $output, $gdb, $layer,
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

    /**
     * Picks the layer carrying both Geo_ID and Deed_No. A geodatabase holds
     * several layers (this one also has Adjacent_Parcel) and only the parcel
     * layer can be imported.
     *
     * @throws ArchiveException
     */
    private function pickLayer(string $gdb): string
    {
        $process = new Process([$this->binary(), '-al', '-so', '-json', $gdb]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            // ogrinfo-style JSON is unavailable on some builds; fall back to the
            // conventional layer name rather than failing outright.
            return 'sakoki_with_deed';
        }

        $info = json_decode($process->getOutput(), true);
        $found = [];

        foreach ($info['layers'] ?? [] as $layer) {
            $name = (string) ($layer['name'] ?? '');
            $found[] = $name;
            $fields = array_map(fn (array $f): string => (string) ($f['name'] ?? ''), $layer['fields'] ?? []);

            if (count(array_intersect(self::REQUIRED_FIELDS, $fields)) === count(self::REQUIRED_FIELDS)) {
                return $name;
            }
        }

        throw new ArchiveException(
            'No layer carrying both Geo_ID and Deed_No was found. Layers present: '.implode(', ', $found)
        );
    }
}
