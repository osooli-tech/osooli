<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DashboardController extends ApiController
{
    /** Summary figures for the app's home screen, scoped to the signed-in owner. */
    public function index(): JsonResponse
    {
        $owner = $this->owner();
        $stats = new OwnerStatisticsService($owner);

        return $this->respond([
            'greeting_name' => Str::before(trim($owner->name), ' '),
            'stats' => $stats->summary(),
            'by_city' => $stats->byCity(),
            'by_district' => $stats->byDistrict(),
            'unread_notifications' => 0,
        ]);
    }
}
