<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalSetting;
use App\Services\Owner\OwnerPortfolioService;
use Illuminate\Support\Facades\DB;

/**
 * The portfolios on the portal home, and narrowing the home to one of them.
 *
 * An account standing for several owners has one portfolio per owner (key
 * "o{id}"); an owner alone has the portfolios they split their land into
 * ("p{id}") and whatever sits in none of them ("rest"). A key that names
 * nothing of theirs is ignored, so the home simply shows everything.
 */
class PortfolioFocus
{
    public const UNASSIGNED = 'rest';

    public function __construct(
        private readonly AccountOverviewService $overviews,
        private readonly OwnerPortfolioService $portfolios,
    ) {}

    /**
     * Call before apply(): the list always shows every portfolio.
     *
     * @return list<array{key: string, name: string, parcels: int, area: float, value: float|null}>
     */
    public function list(Owner $owner, ?PortalAccount $account): array
    {
        $owner->focusOn(null);

        if ($owner->standsForMany() && $account !== null) {
            return array_map(fn (array $card): array => [
                'key' => 'o'.$card['owner_id'],
                'name' => __('portal.linked_portfolio_of', ['name' => $card['name']]),
                'parcels' => $card['parcels'],
                'area' => $card['area'],
                'value' => $card['value'],
            ], $this->overviews->for($account, PortalSetting::allows(PortalSetting::SHOW_VALUE))['owners']);
        }

        $list = $owner->portfolios()->get()->map(function ($portfolio): array {
            $summary = $this->portfolios->summary($portfolio);

            return ['key' => 'p'.$portfolio->id, 'name' => $portfolio->name, 'parcels' => $summary['parcels'], 'area' => $summary['area'], 'value' => $summary['value']];
        })->all();

        $rest = $this->portfolios->unassignedSummary($owner);
        if ($rest['parcels'] > 0) {
            $list[] = [
                'key' => self::UNASSIGNED,
                'name' => $list === [] ? __('portal.linked_portfolio_of', ['name' => $owner->name]) : __('portal.portfolio_unassigned'),
                'parcels' => $rest['parcels'],
                'area' => $rest['area'],
                'value' => $rest['value'],
            ];
        }

        return $list;
    }

    /**
     * Narrows the owner to the portfolio the key names, if it is one of theirs.
     *
     * @param  list<array{key: string, name: string, parcels: int, area: float, value: float|null}>  $list
     * @return array{key: string, name: string, parcels: int, area: float, value: float|null}|null the portfolio now in focus
     */
    public function apply(Owner $owner, ?string $key, array $list): ?array
    {
        $chosen = collect($list)->firstWhere('key', $key);

        // A single portfolio that is everything narrows nothing.
        if ($key === null || $chosen === null || count($list) < 2) {
            return null;
        }

        if (str_starts_with($key, 'o')) {
            $owner->standingFor([(int) substr($key, 1)]);
        } elseif (str_starts_with($key, 'p')) {
            $owner->focusOn(DB::table('owner_portfolio_parcels')->where('owner_portfolio_id', (int) substr($key, 1))->pluck('parcel_id')->all());
        } else {
            $assigned = DB::table('owner_portfolio_parcels')->where('owner_id', $owner->id)->pluck('parcel_id')->all();
            $owner->focusOn($owner->parcels()->whereNotIn('parcels.id', $assigned)->pluck('parcels.id')->all());
        }

        return $chosen;
    }
}
