<?php

declare(strict_types=1);

namespace App\Services\Import;

use Symfony\Component\Process\Process;

/**
 * Enumerates a geodatabase's layers and picks the one carrying both Geo_ID
 * and Deed_No — the parcel layer. A geodatabase can hold several layers
 * (Sakuki.gdb also has Adjacent_Parcel, 1090 neighbouring polygons with
 * neither field) and only the parcel layer can be imported.
 *
 * Layers are enumerated with ogrinfo, not ogr2ogr: `-al`, `-so` and `-json`
 * are ogrinfo flags and ogr2ogr accepts none of them, so probing with
 * ogr2ogr fails on every real GDAL install. GDAL 3.7+ answers `-json`;
 * older builds are handled by falling back to ogrinfo's plain-text layer
 * listing. This never falls back to a hardcoded layer name — if layers
 * cannot be enumerated at all, the failure is loud instead of a silent
 * guess that could write the wrong layer into the live parcels table.
 */
final class GdbLayerPicker
{
    private const REQUIRED_FIELDS = ['Geo_ID', 'Deed_No'];

    /** Field type names ogrinfo's plain-text output prints after a field name. */
    private const FIELD_TYPES = 'Integer64|Integer|Real|String|Date|DateTime|Time|Binary|IntegerList|Integer64List|RealList|StringList|WideString';

    /** @throws ArchiveException */
    public function pick(string $gdb): string
    {
        $json = new Process([$this->binary(), '-al', '-so', '-json', $gdb]);
        $json->setTimeout(120);
        $json->run();

        $layers = $json->isSuccessful() ? $this->parseJsonLayers($json->getOutput()) : null;
        $lastError = $json->getErrorOutput();

        if ($layers === null || $layers === []) {
            // -json is unsupported on GDAL builds before 3.7, or the JSON
            // probe did not produce a usable layer list; fall back to
            // ogrinfo's plain-text listing.
            $text = new Process([$this->binary(), '-al', '-so', $gdb]);
            $text->setTimeout(120);
            $text->run();

            $lastError = $text->getErrorOutput();
            $layers = $text->isSuccessful() ? $this->parseTextLayers($text->getOutput()) : null;

            if ($layers === []) {
                $layers = null;
            }
        }

        if ($layers === null) {
            throw new ArchiveException(
                'Could not determine the geodatabase’s layers: ogrinfo did not return a parseable layer list. '
                .(trim($lastError) !== '' ? trim($lastError) : 'ogrinfo produced no diagnostic output.')
            );
        }

        foreach ($layers as $name => $fields) {
            if (count(array_intersect(self::REQUIRED_FIELDS, $fields)) === count(self::REQUIRED_FIELDS)) {
                return $name;
            }
        }

        throw new ArchiveException(
            'No layer carrying both Geo_ID and Deed_No was found. Layers present: '.implode(', ', array_keys($layers))
        );
    }

    private function binary(): string
    {
        return (string) config('imports.ogrinfo_path', 'ogrinfo');
    }

    /** @return array<string, list<string>>|null */
    private function parseJsonLayers(string $output): ?array
    {
        $info = json_decode($output, true);

        if (! is_array($info) || ! is_array($info['layers'] ?? null)) {
            return null;
        }

        $layers = [];

        foreach ($info['layers'] as $layer) {
            if (! is_array($layer)) {
                continue;
            }

            $name = (string) ($layer['name'] ?? '');
            $rawFields = is_array($layer['fields'] ?? null) ? $layer['fields'] : [];

            $fields = [];
            foreach ($rawFields as $field) {
                $fields[] = is_array($field) ? (string) ($field['name'] ?? '') : '';
            }

            $layers[$name] = $fields;
        }

        return $layers;
    }

    /**
     * Parses ogrinfo's plain-text `-al -so` output (no -json), used on GDAL
     * builds too old to support the JSON summary. A field definition line
     * looks like "Geo_ID: String (50.0)"; layer boundaries are marked by
     * "Layer name: ..." lines.
     *
     * @return array<string, list<string>>
     */
    private function parseTextLayers(string $output): array
    {
        $layers = [];
        $current = null;

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match('/^Layer name:\s*(.+)$/', $line, $matches) === 1) {
                $current = trim($matches[1]);
                $layers[$current] = [];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^(\S+):\s+(?:'.self::FIELD_TYPES.')\b/', trim($line), $matches) === 1) {
                $layers[$current][] = $matches[1];
            }
        }

        return $layers;
    }
}
