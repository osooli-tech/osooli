<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MapAppearanceSetting;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalSetting;
use App\Services\Owner\LinkedParcelsService;
use App\Services\Owner\OwnerInsightsService;
use App\Services\Owner\OwnerPortfolioService;
use App\Services\Owner\OwnerStatisticsService;
use App\Services\Portal\AccountOverviewService;
use App\Support\ParcelMassing;
use App\Support\Portal\PortalContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(OwnerInsightsService $insights, PortalContext $context, AccountOverviewService $overviews): View
    {
        // An account for a family or a company reads all its owners' land here.
        /** @var Owner $owner */
        $owner = $context->readingOwner();
        $account = $context->account();

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
            'ownerPortfolios' => $owner->standsForMany() && $account !== null
                ? $this->accountPortfolios($overviews, $account)
                : $this->ownerPortfolios($owner),
            'linkedGroups' => app(LinkedParcelsService::class)->groups($owner),
            'mapColors' => MapAppearanceSetting::current(),
            'massing' => ParcelMassing::categories(),
            'insights' => $insights->for($owner),
        ]);
    }

    /**
     * One portfolio per owner the account opens, each at that owner's share —
     * a parcel shared by three of them sits in all three.
     *
     * @return list<array{name: string, parcels: int, area: float, value: float|null}>
     */
    private function accountPortfolios(AccountOverviewService $overviews, PortalAccount $account): array
    {
        return array_map(fn (array $card): array => [
            'name' => __('portal.linked_portfolio_of', ['name' => $card['name']]),
            'parcels' => $card['parcels'],
            'area' => $card['area'],
            'value' => $card['value'],
        ], $overviews->for($account, PortalSetting::allows(PortalSetting::SHOW_VALUE))['owners']);
    }

    /**
     * The portfolios the owner's land was split into, then whatever sits in
     * none of them — under the owner's own name when nothing was split, so
     * every owner with land sees at least one portfolio.
     *
     * @return list<array{name: string, parcels: int, area: float, value: float|null}>
     */
    private function ownerPortfolios(Owner $owner): array
    {
        $service = app(OwnerPortfolioService::class);

        $portfolios = $owner->portfolios()->get()
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

        $rest = $service->unassignedSummary($owner);
        if ($rest['parcels'] > 0) {
            $portfolios[] = [
                'name' => $portfolios === []
                    ? __('portal.linked_portfolio_of', ['name' => $owner->name])
                    : __('portal.portfolio_unassigned'),
                'parcels' => $rest['parcels'],
                'area' => $rest['area'],
                'value' => $rest['value'],
            ];
        }

        return $portfolios;
    }
}
