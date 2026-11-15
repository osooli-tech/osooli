<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Owner\OwnerStatisticsService;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(Request $request): JsonResponse
    {
        $owner = $this->readingOwner($request);
        $account = app(PortalContext::class)->account();
        // A company goes by its own name, as on the web portal.
        $greeting = $account?->isCompany() && $account->name
            ? $account->name
            : Str::before(trim($owner->name), ' ');
        // Keyed by every owner read, so the account's figures never serve one owner alone.
        $key = 'owner_dashboard_'.$owner->id.'_'.md5(implode(',', $owner->holderIds()));

        $payload = Cache::remember(
            $key,
            self::CACHE_SECONDS,
            function () use ($owner): array {
                $stats = new OwnerStatisticsService($owner);

                return [
                    'stats' => $stats->summary(),
                    'portfolio' => $stats->portfolio(),
                    'by_city' => $stats->byCity(),
                    'by_district' => $stats->byDistrict(),
                    'by_region' => $stats->byRegion(),
                    'by_owner' => $stats->byOwner(),
                    'unread_notifications' => 0,
                ];
            }
        );

        // The greeting depends on the account, not on the figures: never cached,
        // or an owner's own app could greet them by the name of their agent.
        return $this->respond(['greeting_name' => $greeting, ...$payload]);
    }
}
