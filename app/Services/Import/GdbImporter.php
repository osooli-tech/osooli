<?php

declare(strict_types=1);

namespace App\Services\Import;

/** Composes conversion and parcel import for an uploaded geodatabase. */
final class GdbImporter implements Importer
{
    public function __construct(
        private readonly GdbConverter $converter,
        private readonly ParcelGeoJsonImporter $parcels,
    ) {}

    public function analyze(string $sourcePath): ImportPreview
    {
        return $this->parcels->analyze($this->converter->convert($sourcePath, $this->workDir($sourcePath)));
    }

    public function commit(string $sourcePath): ImportResult
    {
        return $this->parcels->commit($this->converter->convert($sourcePath, $this->workDir($sourcePath)));
    }

    private function workDir(string $sourcePath): string
    {
        return dirname($sourcePath).'/work';
    }
}
