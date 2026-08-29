<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Models\Parcel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code linking a printed parcel report back to its digital twin.
 *
 * Rendered as inline SVG rather than PNG, so dompdf embeds it without needing
 * Imagick or a temporary image file.
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
}
