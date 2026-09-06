<?php

declare(strict_types=1);

namespace App\Services\Parcel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A real satellite photo of the parcel's location, for the print report's
 * "صورة القطعة" box — pulled from Mapbox's Static Images API rather than
 * relying on a ground photo the client may never have uploaded. Returns null
 * on any failure (missing token, no network, bad response) rather than
 * breaking the report; the view falls back to its own placeholder.
 */
class ParcelSatelliteImageService
{
    private const ZOOM = 18;

    private const WIDTH = 500;

    private const HEIGHT = 400;

    public function dataUriFor(float $lat, float $lng): ?string
    {
        $token = config('services.mapbox.token');

        if (! $token) {
            return null;
        }

        $url = sprintf(
            'https://api.mapbox.com/styles/v1/mapbox/satellite-v9/static/%f,%f,%d,0,0/%dx%d@2x',
            $lng,
            $lat,
            self::ZOOM,
            self::WIDTH,
            self::HEIGHT,
        );

        try {
            $response = Http::timeout(10)->get($url, ['access_token' => $token]);

            if (! $response->successful()) {
                Log::warning('Could not fetch satellite image for print report', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return 'data:image/png;base64,'.base64_encode($response->body());
        } catch (\Throwable $e) {
            Log::warning('Could not fetch satellite image for print report', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
