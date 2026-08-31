<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use App\Models\ParcelPhoto;
use Illuminate\Support\Facades\Log;
use Imagick;
use ImagickException;

/**
 * Turns a stored document into a data: URI the print report can embed with a
 * plain <img> tag. A scanned deed or survey sketch is uploaded as PDF and
 * needs its first page rasterised — dompdf cannot embed one PDF's pages
 * inside another. A site photo (aerial/ground) is already an image and is
 * just re-encoded as-is.
 *
 * PDF rasterising requires Imagick built with a PDF delegate (Ghostscript).
 * Confirmed present on production; where it is not — e.g. some local dev
 * setups — a PDF document is skipped rather than the whole report failing,
 * and the report says so. Plain images do not need Imagick at all.
 */
class ParcelDocumentRenderService
{
    private const DPI = 120;

    private const MAX_WIDTH = 1000;

    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function available(): bool
    {
        return extension_loaded('imagick');
    }

    /** Returns a data: URI for an <img> src, or null if it could not be rendered. */
    public function dataUri(ParcelPhoto $document): ?string
    {
        $path = $this->resolvePath($document->photo_url);

        if ($path === null) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return $this->rasterisePdfFirstPage($path, $document->id);
        }

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return $this->encodeImage($path, $extension);
        }

        return null;
    }

    private function rasterisePdfFirstPage(string $path, int $documentId): ?string
    {
        if (! self::available()) {
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
                'document_id' => $documentId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function encodeImage(string $path, string $extension): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = $extension === 'jpg' ? 'jpeg' : $extension;

        return "data:image/{$mime};base64,".base64_encode($contents);
    }

    /** photo_url is a public-disk URL such as /storage/documents/deeds/x.pdf. */
    private function resolvePath(string $photoUrl): ?string
    {
        $relative = ltrim(str_replace('/storage/', '', $photoUrl), '/');
        $path = storage_path('app/public/'.$relative);

        return is_file($path) ? $path : null;
    }
}
