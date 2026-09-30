<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\PhotoType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreModificationRequest;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use App\Queries\OwnerParcelQuery;
use App\Services\Owner\OwnerInsightsService;
use App\Services\Owner\OwnerStatisticsService;
use App\Services\Parcel\DigitalTwinService;
use App\Services\Parcel\ParcelDocumentRenderService;
use App\Services\Parcel\ParcelMapSvgService;
use App\Services\Parcel\ParcelQrCodeService;
use App\Services\Parcel\ParcelSatelliteImageService;
use App\Support\Database\Dialect;
use App\Support\Database\Spatial;
use App\Support\Geo\GeometryMath;
use App\Support\ParcelFrontage;
use App\Support\ParcelMassing;
use App\Support\ParcelStory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only parcel screens for the owner portal. Every query starts from
 * OwnerParcelQuery, so a parcel outside the signed-in owner's own holdings
 * is a plain 404, never a 403 that would confirm it exists.
 */
class ParcelController extends Controller
{
    /** Mirrors the internal ParcelController's own cap on the print report's corner table. */
    private const MAX_PRINTED_CORNERS = 20;

    /** The list itself is the Portal\ParcelIndex Livewire component, embedded in the view. */
    public function index(): View
    {
        $stats = new OwnerStatisticsService($this->owner());

        return view('portal.parcels.index', [
            'summary' => $stats->summary(),
            'portfolio' => $stats->portfolio(),
        ]);
    }

    public function show(int $parcel, OwnerInsightsService $insights, DigitalTwinService $twin): View
    {
        $owner = $this->owner();

        /** @var Parcel $found */
        $found = (new OwnerParcelQuery($owner))
            ->base()
            ->with(['plan.district', 'parent', 'deeds.owners', 'boundary.engineeringOffice', 'surveyDecisions', 'photos'])
            ->findOrFail($parcel);

        $parcelGeojson = $found->getAttribute('geom_json');
        $neighboursGeojson = $this->neighboursGeojson($found, $parcelGeojson);
        $requests = ModificationRequest::where('parcel_id', $found->id)
            ->where('requested_by', $owner->getKey())
            ->latest('id')
            ->get();

        return view('portal.parcels.show', [
            'parcel' => $found,
            'parcelGeojson' => $parcelGeojson,
            'neighboursGeojson' => $neighboursGeojson,
            'modificationRequests' => $requests,
            'story' => ParcelStory::for($found, $requests),
            'editableFields' => StoreModificationRequest::EDITABLE_FIELDS,
            'frontage' => ParcelFrontage::read($found->boundary),
            'massing' => $this->massingOf($found),
            'twin' => $twin->for($found),
            'market' => $found->plan?->district_id === null ? null
                : ($insights->marketAverages(collect([$found->plan->district_id]), $owner->parcels()->pluck('parcels.id'))[$found->plan->district_id] ?? null),
        ]);
    }

    /**
     * How the parcel stands in the 3D mini-map, by its type and latest deed class.
     *
     * @return array{height: float, color: string}
     */
    private function massingOf(Parcel $parcel): array
    {
        return ParcelMassing::styleOf($parcel->asset_type, $parcel->deeds->sortByDesc('id')->first()?->deed_class);
    }

    /** The unified property file, read-only — same as the internal ParcelController::twin(). */
    public function twin(int $parcel, DigitalTwinService $twin, OwnerInsightsService $insights): View
    {
        $owner = $this->owner();

        /** @var Parcel $found */
        $found = (new OwnerParcelQuery($owner))->base()->findOrFail($parcel);

        $parcelGeojson = $found->getAttribute('geom_json');
        $lat = $found->getAttribute('centroid_lat');
        $record = $twin->for($found);
        $requests = ModificationRequest::where('parcel_id', $found->id)->where('requested_by', $owner->getKey())->latest('id')->get();

        return view('portal.parcels.twin', [
            'parcel' => $found,
            'twin' => $record,
            'frontage' => ParcelFrontage::read($found->boundary),
            'story' => ParcelStory::for($found, $requests),
            'market' => $found->plan?->district_id === null ? null
                : ($insights->marketAverages(collect([$found->plan->district_id]), $owner->parcels()->pluck('parcels.id'))[$found->plan->district_id] ?? null),
            'parcelGeojson' => $parcelGeojson,
            'neighboursGeojson' => $this->neighboursGeojson($found, $parcelGeojson),
            'massing' => $this->massingOf($found),
            'centroid' => $lat === null ? null : [
                'lat' => (float) $lat,
                'lng' => (float) $found->getAttribute('centroid_lng'),
            ],
        ]);
    }

    /** A printable one-page report, with a QR code linking back to the portal's own twin page. */
    public function print(
        int $parcel,
        DigitalTwinService $twin,
        ParcelMapSvgService $mapSvg,
        ParcelDocumentRenderService $documentRender,
        ParcelSatelliteImageService $satelliteImage,
        ParcelQrCodeService $qrCode
    ): Response {
        $owner = $this->owner();

        /** @var Parcel $found */
        $found = (new OwnerParcelQuery($owner))
            ->base()
            ->with(['photos', 'heldDeed', 'boundary'])
            ->findOrFail($parcel);
        // The shared print template reads currentDeed; an owner's report shows the deed they hold.
        $found->setRelation('currentDeed', $found->heldDeed);

        $lat = $found->getAttribute('centroid_lat');
        $centroid = $lat === null ? null : [
            'lat' => (float) $lat,
            'lng' => (float) $found->getAttribute('centroid_lng'),
        ];

        $sitePhoto = $centroid !== null
            ? $satelliteImage->dataUriFor($centroid['lat'], $centroid['lng'])
            : null;

        if ($sitePhoto === null) {
            $groundPhoto = $found->photos->firstWhere('photo_type', PhotoType::Ground)
                ?? $found->photos->firstWhere('photo_type', PhotoType::Aerial);
            $sitePhoto = $groundPhoto ? $documentRender->dataUri($groundPhoto) : null;
        }

        $corners = $this->parcelCorners($found);
        $cornersOverflow = count($corners) > self::MAX_PRINTED_CORNERS;

        return Pdf::loadView('exports.parcel-print', [
            'parcel' => $found,
            'twin' => $twin->for($found),
            'qrImage' => $qrCode->pngDataUriForUrl(route('portal.parcels.twin', $found)),
            'mapImage' => $mapSvg->render($found),
            'sitePhoto' => $sitePhoto,
            'reportNumber' => sprintf('SK-%s-%04d', now()->format('Y-m-d'), $found->id),
            'centroid' => $centroid,
            'corners' => $cornersOverflow ? [] : $corners,
            'cornersOverflow' => $cornersOverflow,
            'cornersCount' => count($corners),
        ])->setPaper('a4', 'portrait')
            ->download("parcel-{$found->parcel_no}.pdf");
    }

    /**
     * Corner coordinates in UTM zone 38N — mirrors the internal
     * ParcelController's own parcelCorners().
     *
     * @return list<array{easting: float, northing: float}>
     */
    private function parcelCorners(Parcel $parcel): array
    {
        $isPostgres = Dialect::isPostgres();

        $row = $isPostgres
            ? DB::selectOne('SELECT ST_AsGeoJSON(ST_Transform(geom, 32638)) AS geom_json FROM parcels WHERE id = ?', [$parcel->id])
            : DB::selectOne('SELECT ST_AsGeoJSON(geom) AS geom_json FROM parcels WHERE id = ?', [$parcel->id]);

        if ($row?->geom_json === null) {
            return [];
        }

        $geometry = json_decode($row->geom_json, true);
        $rings = $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'][0] : $geometry['coordinates'];
        $ring = $rings[0] ?? [];

        return collect($ring)
            ->slice(0, -1)
            ->map(fn (array $point): array => $isPostgres
                ? ['easting' => (float) $point[0], 'northing' => (float) $point[1]]
                : GeometryMath::toUtmZone38N((float) $point[0], (float) $point[1]))
            ->values()
            ->all();
    }

    /**
     * Surrounding parcels, drawn faded on the mini-map for context. Mirrors
     * the internal ParcelController's neighboursGeojson() — the same
     * unscoped read, since it exposes nothing beyond a parcel_no and its
     * outline for whatever sits nearby.
     */
    private function neighboursGeojson(Parcel $parcel, ?string $parcelGeojson): string
    {
        /** @var list<\stdClass> $neighbourRows */
        $neighbourRows = $parcelGeojson === null ? [] : DB::select(
            'SELECT n.parcel_no, ST_AsGeoJSON(n.geom, 6) AS geom_json
             FROM parcels n, parcels self
             WHERE self.id = ?
               AND n.id <> self.id
               AND n.geom IS NOT NULL
               AND n.deleted_at IS NULL
               AND '.Spatial::intersectsExpanded('n.geom', 'self.geom', 0.004).'
             LIMIT 60',
            [$parcel->id]
        );

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => array_map(static fn (\stdClass $row): array => [
                'type' => 'Feature',
                'geometry' => json_decode((string) $row->geom_json, false),
                'properties' => ['parcel_no' => $row->parcel_no],
            ], $neighbourRows),
        ]);
    }

    private function owner(): Owner
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return $owner;
    }
}
