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
use App\Services\Parcel\ParcelSatelliteImageService;
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
        ParcelDocumentRenderService $documentRender,
        ParcelSatelliteImageService $satelliteImage
    ): Response {
        $parcel->loadMissing(['photos', 'currentDeed', 'boundary']);

        // A document that cannot be rendered (no Imagick locally, or an
        // unsupported file type) is listed without a preview rather than
        // silently dropped.
        $documents = $parcel->photos->map(fn (ParcelPhoto $photo) => [
            'photo' => $photo,
            'preview' => $documentRender->dataUri($photo),
        ]);

        /** @var \stdClass|null $geoRow */
        $geoRow = DB::selectOne(
            'SELECT ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng FROM parcels WHERE id = ?',
            [$parcel->id]
        );
        $centroid = $geoRow?->lat === null ? null : ['lat' => (float) $geoRow->lat, 'lng' => (float) $geoRow->lng];

        // A real satellite photo reads as "the parcel" more honestly than an
        // uploaded ground shot (rarely on file, and easy to mix up with a
        // neighbouring lot) — preferred whenever the centroid is known, with
        // an actual uploaded photo only as the fallback.
        $sitePhoto = $centroid !== null
            ? $satelliteImage->dataUriFor($centroid['lat'], $centroid['lng'])
            : null;

        if ($sitePhoto === null) {
            $groundPhoto = $parcel->photos->firstWhere('photo_type', PhotoType::Ground)
                ?? $parcel->photos->firstWhere('photo_type', PhotoType::Aerial);
            $sitePhoto = $groundPhoto ? $documentRender->dataUri($groundPhoto) : null;
        }

        /** @return list<array{easting: float, northing: float}> */
        $corners = $this->parcelCorners($parcel);

        return Pdf::loadView('exports.parcel-print', [
            'parcel' => $parcel,
            'twin' => $twin->for($parcel),
            'qrImage' => app(ParcelQrCodeService::class)->pngDataUriFor($parcel),
            'mapImage' => $mapSvg->render($parcel),
            'documents' => $documents,
            'sitePhoto' => $sitePhoto,
            'reportNumber' => sprintf('SK-%s-%04d', now()->format('Y-m-d'), $parcel->id),
            'centroid' => $centroid,
            'corners' => $corners,
        ])->setPaper('a4', 'portrait')
            ->download("parcel-{$parcel->parcel_no}.pdf");
    }

    /**
     * Corner coordinates in UTM (zone 38N, EPSG:32638 — the zone covering the
     * Riyadh region, where every parcel on record currently sits), matching
     * the easting/northing pair a licensed surveyor's plan states rather than
     * raw lat/lng degrees.
     *
     * @return list<array{easting: float, northing: float}>
     */
    private function parcelCorners(Parcel $parcel): array
    {
        /** @var \stdClass|null $row */
        $row = DB::selectOne(
            'SELECT ST_AsGeoJSON(ST_Transform(geom, 32638)) AS geom_json FROM parcels WHERE id = ?',
            [$parcel->id]
        );

        if ($row?->geom_json === null) {
            return [];
        }

        $geometry = json_decode($row->geom_json, true);
        $rings = $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'][0] : $geometry['coordinates'];
        $ring = $rings[0] ?? [];

        // The ring closes back on its first point — that repeat is not a
        // distinct corner.
        return collect($ring)
            ->slice(0, -1)
            ->map(fn (array $point): array => ['easting' => (float) $point[0], 'northing' => (float) $point[1]])
            ->values()
            ->all();
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
