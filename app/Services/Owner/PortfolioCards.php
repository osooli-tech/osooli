<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalSetting;
use App\Services\Portal\AccountOverviewService;

/**
 * The portfolio cards of the owner's home, for the portal and the app alike.
 *
 * kind: `portfolio` (a named one staff split the land into), `unassigned`
 * (what sits in none of them), `owner` (the whole land under the owner's
 * name, when nothing was split — or one per owner for an account that opens
 * several).
 */
class PortfolioCards
{
    public function __construct(
        private readonly OwnerPortfolioService $portfolios,
        private readonly AccountOverviewService $overviews,
    ) {}

    /** @return list<array{name: string, kind: string, parcels: int, area: float, value: float|null}> */
    public function for(Owner $owner, ?PortalAccount $account): array
    {
        return $owner->standsForMany() && $account !== null
            ? $this->ofAccount($account)
            : $this->ofOwner($owner);
    }

    /**
     * One portfolio per owner the account opens, each at that owner's share —
     * a parcel shared by three of them sits in all three.
     *
     * @return list<array{name: string, kind: string, parcels: int, area: float, value: float|null}>
     */
    private function ofAccount(PortalAccount $account): array
    {
        return array_map(fn (array $card): array => [
            'name' => __('portal.linked_portfolio_of', ['name' => $card['name']]),
            'kind' => 'owner',
            'parcels' => $card['parcels'],
            'area' => $card['area'],
            'value' => $card['value'],
        ], $this->overviews->for($account, PortalSetting::allows(PortalSetting::SHOW_VALUE))['owners']);
    }

    /**
     * The portfolios the owner's land was split into, then whatever sits in
     * none of them — under the owner's own name when nothing was split, so
     * every owner with land sees at least one portfolio.
     *
     * @return list<array{name: string, kind: string, parcels: int, area: float, value: float|null}>
     */
    private function ofOwner(Owner $owner): array
    {
        $cards = $owner->portfolios()->get()
            ->map(function ($portfolio): array {
                $summary = $this->portfolios->summary($portfolio);

                return [
                    'name' => $portfolio->name,
                    'kind' => 'portfolio',
                    'parcels' => $summary['parcels'],
                    'area' => $summary['area'],
                    'value' => $summary['value'],
                ];
            })
            ->all();

        $rest = $this->portfolios->unassignedSummary($owner);
        if ($rest['parcels'] > 0) {
            $cards[] = [
                'name' => $cards === []
                    ? __('portal.linked_portfolio_of', ['name' => $owner->name])
                    : __('portal.portfolio_unassigned'),
                'kind' => $cards === [] ? 'owner' : 'unassigned',
                'parcels' => $rest['parcels'],
                'area' => $rest['area'],
                'value' => $rest['value'],
            ];
        }

        return array_values($cards);
    }
}
