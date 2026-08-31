<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Models\Parcel;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Imagick;

/**
 * A QR code linking a printed parcel report back to its digital twin.
 *
 * svgFor() is kept for callers that render in a browser (SVG is crisp and
 * tiny there), but the print report needs pngDataUriFor(): dompdf 3.x has no
 * SVG renderer at all — confirmed by testing a bare <svg><rect></svg> in
 * isolation, which produced nothing — so every image embedded in a PDF here
 * has to be a raster format instead. Requires Imagick like every other
 * rasterised image in the print report; returns null where it is missing
 * rather than throwing.
 */
class ParcelQrCodeService
{
    public function svgFor(Parcel $parcel): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString(route('parcels.twin', $parcel));

        // Strip the XML prolog: it is only valid at the very start of a
        // document, and this SVG is embedded inside an HTML page.
        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    public function pngDataUriFor(Parcel $parcel): ?string
    {
        if (! class_exists(Imagick::class)) {
            return null;
        }

        $renderer = new ImageRenderer(new RendererStyle(220), new ImagickImageBackEnd);
        $png = (new Writer($renderer))->writeString(route('parcels.twin', $parcel));

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
