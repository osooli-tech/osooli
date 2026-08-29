<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Models\Parcel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * A static, printable map of one parcel among its neighbours — for the print
 * report, which dompdf renders with no JavaScript, so the interactive Mapbox
 * map cannot appear there.
 *
 * Coordinates are reprojected to local metres around the parcel's own
 * centroid rather than used as raw degrees: over the few hundred metres a
 * parcel and its neighbours span, that keeps shapes undistorted and makes the
 * scale bar and north arrow meaningful, which plotting raw lng/lat does not.
 *
 * The map is built as SVG but handed to the print view as a rasterised PNG
 * data URI, not raw markup: dompdf's own inline-SVG support renders a
 * single-path image (the QR code) fine, but silently drops every shape here —
 * polygons, lines, text — falling back to dumping the SVG's text nodes as
 * plain unpositioned text. Imagick (confirmed present in production, with the
 * delegate this needs) rasterises the same SVG correctly, so that renders it
 * instead of dompdf.
 */
class ParcelMapSvgService
{
    private const CANVAS = 720;

    private const PADDING = 60;

    private const METRES_PER_DEGREE_LAT = 111320.0;

    /** Returns a data: URI for an <img> src, or null if there is nothing to draw. */
    public function render(Parcel $parcel): ?string
    {
        $svg = $this->buildForParcel($parcel);

        return $svg === null ? null : $this->rasterise($svg);
    }

    private function rasterise(string $svg): ?string
    {
        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $imagick = new Imagick;
            $imagick->setBackgroundColor('transparent');
            $imagick->readImageBlob($svg);
            $imagick->setImageFormat('png');

            $blob = $imagick->getImageBlob();
            $imagick->destroy();

            return 'data:image/png;base64,'.base64_encode($blob);
        } catch (ImagickException $e) {
            Log::warning('Could not rasterise the parcel map SVG', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildForParcel(Parcel $parcel): ?string
    {
        /** @var \stdClass|null $row */
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom, 6) AS geom_json FROM parcels WHERE id = ? AND geom IS NOT NULL',
            [$parcel->id]
        );

        if ($row === null) {
            return null;
        }

        $targetGeometry = json_decode((string) $row->geom_json, true);
        $targetRings = $this->rings($targetGeometry);

        if ($targetRings === []) {
            return null;
        }

        /** @var list<\stdClass> $neighbourRows */
        $neighbourRows = DB::select(
            'SELECT n.parcel_no, ST_AsGeoJSON(n.geom, 6) AS geom_json
             FROM parcels n, parcels self
             WHERE self.id = ?
               AND n.id <> self.id
               AND n.geom IS NOT NULL
               AND ST_Intersects(n.geom, ST_Expand(self.geom, 0.003))
             LIMIT 40',
            [$parcel->id]
        );

        $centroid = $this->centroid($targetRings[0]);
        $metresPerDegreeLng = self::METRES_PER_DEGREE_LAT * cos(deg2rad($centroid['lat']));

        $project = function (array $lngLat) use ($centroid, $metresPerDegreeLng): array {
            return [
                'x' => ($lngLat[0] - $centroid['lng']) * $metresPerDegreeLng,
                // North is "up": SVG y grows downward, so latitude is negated.
                'y' => -(($lngLat[1] - $centroid['lat']) * self::METRES_PER_DEGREE_LAT),
            ];
        };

        $targetShapes = array_map(
            fn (array $ring) => array_map($project, $ring),
            $targetRings
        );

        $neighbours = [];
        foreach ($neighbourRows as $n) {
            $rings = $this->rings(json_decode((string) $n->geom_json, true));
            if ($rings === []) {
                continue;
            }
            $neighbours[] = [
                'parcel_no' => $n->parcel_no,
                'shapes' => array_map(fn (array $ring) => array_map($project, $ring), $rings),
            ];
        }

        $allPoints = array_merge(
            ...$targetShapes,
            ...array_map(fn (array $n) => array_merge(...$n['shapes']), $neighbours)
        );

        $xs = array_column($allPoints, 'x');
        $ys = array_column($allPoints, 'y');
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        $extentX = max($maxX - $minX, 1.0);
        $extentY = max($maxY - $minY, 1.0);
        $drawable = self::CANVAS - 2 * self::PADDING;
        $scale = min($drawable / $extentX, $drawable / $extentY);

        $toSvg = function (array $point) use ($minX, $minY, $extentX, $extentY, $scale): array {
            $usedW = $extentX * $scale;
            $usedH = $extentY * $scale;
            // Centre the drawing within the padded canvas on both axes.
            $offsetX = self::PADDING + (($drawable = self::CANVAS - 2 * self::PADDING) - $usedW) / 2;
            $offsetY = self::PADDING + ($drawable - $usedH) / 2;

            return [
                'x' => $offsetX + ($point['x'] - $minX) * $scale,
                'y' => $offsetY + ($point['y'] - $minY) * $scale,
            ];
        };

        return $this->buildSvg($targetShapes, $neighbours, $toSvg, $scale, $parcel->parcel_no);
    }

    /** @return list<list<array{0: float, 1: float}>> exterior rings only, per polygon */
    private function rings(?array $geometry): array
    {
        if ($geometry === null) {
            return [];
        }

        $polygons = $geometry['type'] === 'MultiPolygon'
            ? $geometry['coordinates']
            : [$geometry['coordinates']];

        // The exterior ring is the first in each polygon; interior rings
        // (holes) are not meaningful at this scale and are dropped.
        return array_map(static fn (array $polygon): array => $polygon[0], $polygons);
    }

    /** @param list<array{0: float, 1: float}> $ring */
    private function centroid(array $ring): array
    {
        $count = count($ring);
        $sumLng = array_sum(array_column($ring, 0));
        $sumLat = array_sum(array_column($ring, 1));

        return ['lng' => $sumLng / $count, 'lat' => $sumLat / $count];
    }

    /**
     * @param  list<list<array{x: float, y: float}>>  $targetShapes
     * @param  list<array{parcel_no: string, shapes: list<list<array{x: float, y: float}>>}>  $neighbours
     */
    private function buildSvg(
        array $targetShapes,
        array $neighbours,
        \Closure $toSvg,
        float $scale,
        ?string $targetParcelNo
    ): string {
        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d">',
            self::CANVAS
        );
        $svg[] = sprintf('<rect x="0" y="0" width="%1$d" height="%1$d" fill="#f8f9ff" stroke="#ddd"/>', self::CANVAS);

        foreach ($neighbours as $n) {
            foreach ($n['shapes'] as $ring) {
                $svg[] = $this->polygon($ring, $toSvg, '#c7cdd9', 1.0, '#8a95a8', 1);
            }
            $label = $this->ringCenter($n['shapes'][0], $toSvg);
            $svg[] = sprintf(
                '<text x="%.1f" y="%.1f" font-size="13" fill="#5a6478" text-anchor="middle" font-family="DejaVu Sans">%s</text>',
                $label['x'],
                $label['y'],
                htmlspecialchars((string) $n['parcel_no'], ENT_QUOTES)
            );
        }

        foreach ($targetShapes as $ring) {
            $svg[] = $this->polygon($ring, $toSvg, '#00b386', 0.4, '#006c4e', 3);
        }
        if ($targetShapes !== []) {
            $label = $this->ringCenter($targetShapes[0], $toSvg);
            $svg[] = sprintf(
                '<text x="%.1f" y="%.1f" font-size="15" font-weight="bold" fill="#002444" text-anchor="middle" font-family="DejaVu Sans">%s</text>',
                $label['x'],
                $label['y'],
                htmlspecialchars((string) $targetParcelNo, ENT_QUOTES)
            );
        }

        $svg[] = $this->northArrow();
        $svg[] = $this->scaleBar($scale);
        $svg[] = $this->legend();

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /**
     * @param  list<array{x: float, y: float}>  $ring
     *
     * $fillOpacity is a separate attribute rather than an 8-digit alpha hex
     * (#rrggbbaa): dompdf's SVG renderer does not recognise that CSS Color
     * Level 4 syntax and silently drops the whole element — the polygons
     * never appeared on the page at all until this was split out.
     */
    private function polygon(array $ring, \Closure $toSvg, string $fill, float $fillOpacity, string $stroke, int $width): string
    {
        $points = implode(' ', array_map(
            static fn (array $p) => sprintf('%.1f,%.1f', $toSvg($p)['x'], $toSvg($p)['y']),
            $ring
        ));

        return sprintf(
            '<polygon points="%s" fill="%s" fill-opacity="%.2f" stroke="%s" stroke-width="%d" stroke-linejoin="round"/>',
            $points,
            $fill,
            $fillOpacity,
            $stroke,
            $width
        );
    }

    /** @param list<array{x: float, y: float}> $ring */
    private function ringCenter(array $ring, \Closure $toSvg): array
    {
        $svgPoints = array_map($toSvg, $ring);

        return [
            'x' => array_sum(array_column($svgPoints, 'x')) / count($svgPoints),
            'y' => array_sum(array_column($svgPoints, 'y')) / count($svgPoints),
        ];
    }

    private function northArrow(): string
    {
        $x = self::CANVAS - 44;
        $y = 44;

        return sprintf(
            '<g transform="translate(%1$d,%2$d)">'
            .'<polygon points="0,-20 8,10 0,3 -8,10" fill="#002444"/>'
            .'<text x="0" y="30" font-size="13" font-weight="bold" fill="#002444" text-anchor="middle" font-family="DejaVu Sans">N</text>'
            .'</g>',
            $x,
            $y
        );
    }

    /** A round metre value whose bar fits comfortably within the canvas. */
    private function scaleBar(float $pixelsPerMetre): string
    {
        $candidates = [5, 10, 20, 25, 50, 100, 150, 200, 250, 500];
        $maxBarPx = 160;

        $metres = 20;
        foreach ($candidates as $candidate) {
            if ($candidate * $pixelsPerMetre <= $maxBarPx) {
                $metres = $candidate;
            }
        }

        $barPx = $metres * $pixelsPerMetre;
        $x0 = self::PADDING;
        $y = self::CANVAS - 28;

        return sprintf(
            '<g>'
            .'<line x1="%1$.1f" y1="%2$d" x2="%3$.1f" y2="%2$d" stroke="#002444" stroke-width="2"/>'
            .'<line x1="%1$.1f" y1="%4$d" x2="%1$.1f" y2="%5$d" stroke="#002444" stroke-width="2"/>'
            .'<line x1="%3$.1f" y1="%4$d" x2="%3$.1f" y2="%5$d" stroke="#002444" stroke-width="2"/>'
            .'<text x="%6$.1f" y="%7$d" font-size="12" fill="#002444" text-anchor="middle" font-family="DejaVu Sans">%8$d %9$s</text>'
            .'</g>',
            $x0,
            $y,
            $x0 + $barPx,
            $y - 5,
            $y + 5,
            $x0 + $barPx / 2,
            $y + 20,
            $metres,
            __('parcels.map_unit_metres')
        );
    }

    private function legend(): string
    {
        $x = self::PADDING;
        $y = 30;

        return sprintf(
            '<g font-family="DejaVu Sans" font-size="12" fill="#002444">'
            .'<rect x="%1$d" y="%2$d" width="14" height="14" fill="#00b38666" stroke="#006c4e" stroke-width="2"/>'
            .'<text x="%3$d" y="%4$d">%5$s</text>'
            .'<rect x="%1$d" y="%6$d" width="14" height="14" fill="#c7cdd9" stroke="#8a95a8"/>'
            .'<text x="%3$d" y="%7$d">%8$s</text>'
            .'</g>',
            $x,
            $y,
            $x + 20,
            $y + 12,
            __('parcels.map_legend_target'),
            $y + 22,
            $y + 34,
            __('parcels.map_legend_neighbours')
        );
    }
}
