<?php

declare(strict_types=1);

namespace App\Support\Import;

use Generator;
use RuntimeException;

/**
 * Reads a GeoJSON FeatureCollection one feature at a time.
 *
 * An export of twenty thousand deeds is tens of megabytes, and json_decode()
 * on the whole file needs several times that in memory. This walks the file
 * in blocks, tracking brace depth and strings, and hands back each element
 * of the top-level "features" array decoded on its own — memory stays at
 * one feature however large the file.
 *
 * It also returns the collection's other top-level members (our "sokuki"
 * header among them), read from the part of the file before "features".
 */
final class GeoJsonFeatureStream
{
    private const BLOCK = 1 << 20;

    /** @var array<string, mixed> */
    private array $header = [];

    public function __construct(private readonly string $path) {}

    /**
     * Top-level members other than "features" that precede it — our export
     * writes its "sokuki" header there. Filled once iteration has begun.
     *
     * @return array<string, mixed>
     */
    public function header(): array
    {
        return $this->header;
    }

    /** @return Generator<int, array<string, mixed>> */
    public function features(): Generator
    {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open the uploaded file.');
        }

        try {
            // Everything before the features array, which holds the header.
            $prefix = '';
            $offset = null;
            while (! feof($handle) && $offset === null) {
                $prefix .= (string) fread($handle, self::BLOCK);
                if (preg_match('/"features"\s*:\s*\[/', $prefix, $m, PREG_OFFSET_CAPTURE)) {
                    $offset = $m[0][1] + strlen($m[0][0]);
                }
                if ($offset === null && strlen($prefix) > 8 * self::BLOCK) {
                    break;
                }
            }

            if ($offset === null) {
                throw new RuntimeException('not_feature_collection');
            }

            $this->header = $this->readHeader(substr($prefix, 0, $offset));
            $buffer = substr($prefix, $offset);
            unset($prefix);

            yield from $this->elements($handle, $buffer);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, array<string, mixed>>
     */
    private function elements($handle, string $buffer): Generator
    {
        $index = 0;
        $depth = 0;
        $inString = false;
        $escaped = false;
        $start = null;
        $position = 0;

        while (true) {
            $length = strlen($buffer);

            for (; $position < $length; $position++) {
                $char = $buffer[$position];

                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '"') {
                        $inString = false;
                    } else {
                        // Skip straight to the next quote or backslash.
                        $skip = strcspn($buffer, '"\\', $position + 1);
                        $position += $skip;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                } elseif ($char === '{' || $char === '[') {
                    if ($depth === 0) {
                        $start = $position;
                    }
                    $depth++;
                } elseif ($char === '}' || $char === ']') {
                    if ($depth === 0) {
                        return; // the closing bracket of "features"
                    }
                    $depth--;
                    if ($depth === 0 && $start !== null) {
                        $feature = json_decode(substr($buffer, $start, $position - $start + 1), true);
                        if (! is_array($feature)) {
                            throw new RuntimeException('invalid_feature:'.$index);
                        }
                        yield $index++ => $feature;
                        $start = null;
                    }
                }
            }

            if (feof($handle)) {
                if ($depth !== 0) {
                    throw new RuntimeException('truncated');
                }

                return;
            }

            // Keep only the unfinished feature, then read on.
            if ($start !== null) {
                $buffer = substr($buffer, $start);
                $position -= $start;
                $start = 0;
            } else {
                $buffer = '';
                $position = 0;
            }
            $buffer .= (string) fread($handle, self::BLOCK);
        }
    }

    /** @return array<string, mixed> */
    private function readHeader(string $prefix): array
    {
        // Close the object where "features" would have begun and decode that.
        $object = rtrim(preg_replace('/,?\s*"features"\s*:\s*\[$/', '', rtrim($prefix)) ?? '');
        $decoded = json_decode($object.'}', true);

        return is_array($decoded) ? $decoded : [];
    }
}
