<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PhotoType;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Services\Parcel\DigitalTwinService;
use App\Services\Parcel\ParcelDocumentRenderService;
use App\Services\Parcel\ParcelMapSvgService;
use App\Services\Parcel\ParcelQrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ParcelController extends Controller
{
    public function show(Parcel $parcel): View
    {
        $parcel->load([
            'plan.district',
            'deeds.owners',
            'boundary.engineeringOffice',
            'surveyDecisions',
            'photos',
        ]);

        /** @var \stdClass|null $geoRow */
        $geoRow = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom, 6) AS geom_json FROM parcels WHERE id = ?',
            [$parcel->id]
        );
        $parcelGeojson = $geoRow?->geom_json;

        // Surrounding parcels, drawn faded on the mini-map so the parcel can be
        // read in context. Limited to what falls inside a small buffer around it.
        /** @var list<\stdClass> $neighbourRows */
        $neighbourRows = $parcelGeojson === null ? [] : DB::select(
            'SELECT n.parcel_no, ST_AsGeoJSON(n.geom, 6) AS geom_json
             FROM parcels n, parcels self
             WHERE self.id = ?
               AND n.id <> self.id
               AND n.geom IS NOT NULL
               AND ST_Intersects(n.geom, ST_Expand(self.geom, 0.004))
             LIMIT 60',
            [$parcel->id]
        );

        $neighboursGeojson = json_encode([
            'type' => 'FeatureCollection',
            'features' => array_map(static fn (\stdClass $row): array => [
                'type' => 'Feature',
                'geometry' => json_decode((string) $row->geom_json, false),
                'properties' => ['parcel_no' => $row->parcel_no],
            ], $neighbourRows),
        ]);

        return view('parcels.show', compact('parcel', 'parcelGeojson', 'neighboursGeojson'));
    }

    /** The unified property file: every record we hold on one parcel, in one view. */
    public function twin(Parcel $parcel, DigitalTwinService $twin): View
    {
        /** @var \stdClass|null $geoRow */
        $geoRow = DB::selectOne(
            'SELECT ST_AsGeoJSON(geom, 6) AS geom_json, ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng
             FROM parcels WHERE id = ?',
            [$parcel->id]
        );

        return view('parcels.twin', [
            'parcel' => $parcel,
            'twin' => $twin->for($parcel),
            'parcelGeojson' => $geoRow?->geom_json,
            'centroid' => $geoRow?->lat === null ? null : ['lat' => (float) $geoRow->lat, 'lng' => (float) $geoRow->lng],
        ]);
    }

    /** A branded, printable one-page report for the parcel, with a QR code
     *  linking back to its digital twin so a printed copy stays traceable. */
    public function print(
        Parcel $parcel,
        DigitalTwinService $twin,
        ParcelMapSvgService $mapSvg,
        ParcelDocumentRenderService $documentRender
    ): Response {
        $parcel->loadMissing(['photos', 'currentDeed']);

        // A document that cannot be rendered (no Imagick locally, or an
        // unsupported file type) is listed without a preview rather than
        // silently dropped.
        $documents = $parcel->photos->map(fn (ParcelPhoto $photo) => [
            'photo' => $photo,
            'preview' => $documentRender->dataUri($photo),
        ]);

        // A ground-level shot reads as "the parcel" more than an aerial one;
        // aerial is the fallback rather than left out.
        $sitePhoto = $parcel->photos->firstWhere('photo_type', PhotoType::Ground)
            ?? $parcel->photos->firstWhere('photo_type', PhotoType::Aerial);

        /** @var \stdClass|null $geoRow */
        $geoRow = DB::selectOne(
            'SELECT ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng FROM parcels WHERE id = ?',
            [$parcel->id]
        );

        return Pdf::loadView('exports.parcel-print', [
            'parcel' => $parcel,
            'twin' => $twin->for($parcel),
            'qrImage' => app(ParcelQrCodeService::class)->pngDataUriFor($parcel),
            'mapImage' => $mapSvg->render($parcel),
            'documents' => $documents,
            'sitePhoto' => $sitePhoto ? $documentRender->dataUri($sitePhoto) : null,
            'reportNumber' => sprintf('SK-%s-%04d', now()->format('Y-m-d'), $parcel->id),
            'centroid' => $geoRow?->lat === null ? null : ['lat' => (float) $geoRow->lat, 'lng' => (float) $geoRow->lng],
        ])->setPaper('a4', 'portrait')
            ->download("parcel-{$parcel->parcel_no}.pdf");
    }

    public function documents(Parcel $parcel): JsonResponse
    {
        $documents = $parcel->photos()->get()->map(fn (ParcelPhoto $photo) => [
            'id' => $photo->id,
            'type' => $photo->photo_type?->value,
            'download_url' => route('documents.download', $photo),
        ]);

        return response()->json(['documents' => $documents]);
    }
}
