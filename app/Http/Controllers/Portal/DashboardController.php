<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MapAppearanceSetting;
use App\Models\Owner;
use App\Services\Owner\OwnerInsightsService;
use App\Services\Owner\OwnerPortfolioService;
use App\Services\Owner\OwnerStatisticsService;
use App\Support\ParcelMassing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(OwnerInsightsService $insights): View
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        $stats = new OwnerStatisticsService($owner);

        return view('portal.dashboard', [
            'greetingName' => Str::before(trim($owner->name), ' '),
            'summary' => $stats->summary(),
            'portfolio' => $stats->portfolio(),
            'areaExtremes' => $stats->areaExtremes(),
            'plansCount' => $stats->plansCount(),
            'byAssetType' => $stats->byAssetType(),
            'byCity' => $stats->byCity(),
            'byDistrict' => $stats->byDistrict(),
            'byQrarSource' => $stats->byQrarSource(),
            'byEngineeringOffice' => $stats->byEngineeringOffice(),
            'ownerPortfolios' => $this->ownerPortfolios($owner),
            'mapColors' => MapAppearanceSetting::current(),
            'massing' => ParcelMassing::categories(),
            'insights' => $insights->for($owner),
        ]);
    }

    /** @return list<array{name: string, parcels: int, area: float, value: float|null}> */
    private function ownerPortfolios(Owner $owner): array
    {
        $service = app(OwnerPortfolioService::class);

        return $owner->portfolios()->get()
            ->map(function ($portfolio) use ($service): array {
                $summary = $service->summary($portfolio);

                return [
                    'name' => $portfolio->name,
                    'parcels' => $summary['parcels'],
                    'area' => $summary['area'],
                    'value' => $summary['value'],
                ];
            })
            ->all();
    }
}
