<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Models\ParcelPhoto;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Rasterises the first page of a stored document (almost always a scanned
 * deed or survey sketch, uploaded as PDF) into a PNG the print report can
 * embed with a plain <img> tag — dompdf cannot embed one PDF's pages inside
 * another.
 *
 * Requires Imagick built with a PDF delegate (Ghostscript). Confirmed present
 * on production; where it is not — e.g. some local dev setups — a document
 * is skipped rather than the whole report failing, and the report says so.
 */
class ParcelDocumentRenderService
{
    private const DPI = 120;

    private const MAX_WIDTH = 1000;

    public static function available(): bool
    {
        return extension_loaded('imagick');
    }

    /** Returns a data: URI for an <img> src, or null if it could not be rendered. */
    public function firstPageDataUri(ParcelPhoto $document): ?string
    {
        if (! self::available()) {
            return null;
        }

        $path = $this->resolvePath($document->photo_url);

        if ($path === null || ! str_ends_with(strtolower($path), '.pdf')) {
            return null;
        }

        try {
            $imagick = new Imagick;
            $imagick->setResolution(self::DPI, self::DPI);
            // [0] selects only the first page — a multi-page deed scan does
            // not need every page in a summary report.
            $imagick->readImage($path.'[0]');
            $imagick->setImageFormat('png');
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

            if ($imagick->getImageWidth() > self::MAX_WIDTH) {
                $imagick->scaleImage(self::MAX_WIDTH, 0);
            }

            $blob = $imagick->getImageBlob();
            $imagick->destroy();

            return 'data:image/png;base64,'.base64_encode($blob);
        } catch (ImagickException $e) {
            Log::warning('Could not rasterise document for print report', [
                'document_id' => $document->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** photo_url is a public-disk URL such as /storage/documents/deeds/x.pdf. */
    private function resolvePath(string $photoUrl): ?string
    {
        $relative = ltrim(str_replace('/storage/', '', $photoUrl), '/');
        $path = storage_path('app/public/'.$relative);

        return is_file($path) ? $path : null;
    }
}
