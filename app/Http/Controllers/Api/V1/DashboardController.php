<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DashboardController extends ApiController
{
    /**
     * How long the aggregated figures may be served from cache. Holdings
     * change through slow back-office imports, so a minute of staleness is
     * invisible to owners while cutting ~8 aggregate queries per open.
     */
    private const CACHE_SECONDS = 60;

    /** Summary figures for the app's home screen, scoped to the signed-in owner. */
    public function index(): JsonResponse
    {
        $owner = $this->owner();

        $payload = Cache::remember(
            "owner_dashboard_{$owner->id}",
            self::CACHE_SECONDS,
            function () use ($owner): array {
                $stats = new OwnerStatisticsService($owner);

                return [
                    'greeting_name' => Str::before(trim($owner->name), ' '),
                    'stats' => $stats->summary(),
                    'by_city' => $stats->byCity(),
                    'by_district' => $stats->byDistrict(),
                    'unread_notifications' => 0,
                ];
            }
        );

        return $this->respond($payload);
    }
}
