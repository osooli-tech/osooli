<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Parcel;
use App\Models\ParcelPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

        return view('parcels.show', compact('parcel', 'parcelGeojson'));
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
