<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MapAppearanceSetting;
use App\Models\Owner;
use App\Services\Owner\LinkedParcelsService;
use App\Services\Owner\OwnerInsightsService;
use App\Services\Owner\OwnerStatisticsService;
use App\Services\Portal\PortfolioFocus;
use App\Support\ParcelMassing;
use App\Support\Portal\PortalContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request, OwnerInsightsService $insights, PortalContext $context, PortfolioFocus $focus): View
    {
        // An account for a family or a company reads all its owners' land here.
        /** @var Owner $owner */
        $owner = $context->readingOwner();
        $account = $context->account();

        // Listed before narrowing, so every portfolio stays on the page.
        $portfolios = $focus->list($owner, $account);
        $activePortfolio = $focus->apply($owner, $request->string('portfolio')->toString() ?: null, $portfolios);

        $stats = new OwnerStatisticsService($owner);

        return view('portal.dashboard', [
            'greetingName' => $account?->isCompany() && $account->name ? $account->name : Str::before(trim($owner->name), ' '),
            'summary' => $stats->summary(),
            'portfolio' => $stats->portfolio(),
            'areaExtremes' => $stats->areaExtremes(),
            'plansCount' => $stats->plansCount(),
            'byAssetType' => $stats->byAssetType(),
            'byCity' => $stats->byCity(),
            'byDistrict' => $stats->byDistrict(),
            'byQrarSource' => $stats->byQrarSource(),
            'byEngineeringOffice' => $stats->byEngineeringOffice(),
            'ownerPortfolios' => $portfolios,
            'activePortfolio' => $activePortfolio,
            'linkedGroups' => $activePortfolio === null ? app(LinkedParcelsService::class)->groups($owner) : [],
            'mapColors' => MapAppearanceSetting::current(),
            'massing' => ParcelMassing::categories(),
            'insights' => $insights->for($owner),
        ]);
    }
}
