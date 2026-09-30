<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A parcel's real outline as a small inline SVG, drawn from its GeoJSON.
 * Longitude is scaled by cos(latitude) so shapes keep their true proportions.
 */
class ParcelShape extends Component
{
    private const VIEWBOX = 100;

    private const PADDING = 8;

    public string $path = '';

    public function __construct(?string $geojson = null)
    {
        $geometry = $geojson === null ? null : json_decode($geojson, true);

        if (is_array($geometry)) {
            $this->path = $this->buildPath($geometry);
        }
    }

    /** @param array<string, mixed> $geometry */
    private function buildPath(array $geometry): string
    {
        $polygons = match ($geometry['type'] ?? null) {
            'MultiPolygon' => $geometry['coordinates'],
            'Polygon' => [$geometry['coordinates']],
            default => [],
        };

        $rings = array_values(array_filter(array_map(static fn (array $polygon) => $polygon[0] ?? null, $polygons)));
        $points = array_merge(...$rings ?: [[]]);

        if ($points === []) {
            return '';
        }

        $lngs = array_column($points, 0);
        $lats = array_column($points, 1);
        $scaleX = cos(deg2rad((min($lats) + max($lats)) / 2));
        $width = max((max($lngs) - min($lngs)) * $scaleX, 1e-9);
        $height = max(max($lats) - min($lats), 1e-9);
        $scale = (self::VIEWBOX - 2 * self::PADDING) / max($width, $height);
        $offsetX = (self::VIEWBOX - $width * $scale) / 2;
        $offsetY = (self::VIEWBOX - $height * $scale) / 2;

        $d = '';
        foreach ($rings as $ring) {
            foreach ($ring as $i => $point) {
                $x = $offsetX + ($point[0] - min($lngs)) * $scaleX * $scale;
                $y = $offsetY + (max($lats) - $point[1]) * $scale;
                $d .= ($i === 0 ? 'M' : 'L').round($x, 2).' '.round($y, 2).' ';
            }
            $d .= 'Z ';
        }

        return trim($d);
    }

    public function render(): View
    {
        return view('components.parcel-shape');
    }
}
