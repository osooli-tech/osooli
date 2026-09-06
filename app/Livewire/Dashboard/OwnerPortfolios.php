<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\OwnerPortfolio;
use App\Services\Owner\OwnerPortfolioService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The city grouping above answers "how much do we hold in each city"; this
 * answers a different question — "what has each owner organised their own
 * holdings into" — since an owner's portfolios are their own named split of
 * their parcels (e.g. by project), not derived from geography at all.
 */
class OwnerPortfolios extends Component
{
    private const LIMIT = 12;

    /** @var list<array{owner: string, name: string, parcels: int, area: float, value: float|null, priced: int}> */
    public array $portfolios = [];

    public int $totalCount = 0;

    public function mount(OwnerPortfolioService $service): void
    {
        $all = OwnerPortfolio::with('owner')->withCount('parcels')->get();
        $this->totalCount = $all->count();

        $this->portfolios = $all
            ->sortByDesc('parcels_count')
            ->take(self::LIMIT)
            ->map(function (OwnerPortfolio $portfolio) use ($service): array {
                $summary = $service->summary($portfolio);

                return [
                    'owner' => $portfolio->owner->name,
                    'name' => $portfolio->name,
                    'parcels' => $summary['parcels'],
                    'area' => $summary['area'],
                    'priced' => $summary['priced'],
                    'value' => $summary['value'],
                ];
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.dashboard.owner-portfolios');
    }
}
