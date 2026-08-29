<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Models\Owner;
use App\Models\Parcel;
use App\Services\Owner\OwnerPortfolioService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PortfolioManager extends Component
{
    #[Locked]
    public int $ownerId;

    public string $newPortfolioName = '';

    /** @var array<int, string> */
    public array $renaming = [];

    public function mount(int $ownerId): void
    {
        $this->ownerId = $ownerId;
    }

    public function createPortfolio(OwnerPortfolioService $service): void
    {
        $this->validate(['newPortfolioName' => ['required', 'string', 'max:100']], [], [
            'newPortfolioName' => __('owners.portfolio_name'),
        ]);

        $service->create($this->owner(), $this->newPortfolioName);
        $this->newPortfolioName = '';
    }

    public function renamePortfolio(int $portfolioId, OwnerPortfolioService $service): void
    {
        $name = trim($this->renaming[$portfolioId] ?? '');

        if ($name === '') {
            return;
        }

        $portfolio = $this->owner()->portfolios()->findOrFail($portfolioId);
        $service->rename($portfolio, $name);
        unset($this->renaming[$portfolioId]);
    }

    public function deletePortfolio(int $portfolioId, OwnerPortfolioService $service): void
    {
        $portfolio = $this->owner()->portfolios()->findOrFail($portfolioId);
        $service->delete($portfolio);
    }

    public function assign(int $parcelId, string $portfolioId, OwnerPortfolioService $service): void
    {
        $parcel = Parcel::findOrFail($parcelId);

        if ($portfolioId === '') {
            $service->unassign($this->owner(), $parcel);

            return;
        }

        $portfolio = $this->owner()->portfolios()->findOrFail((int) $portfolioId);
        $service->assign($this->owner(), $parcel, $portfolio);
    }

    public function render(OwnerPortfolioService $service): View
    {
        $owner = $this->owner();

        return view('livewire.owners.portfolio-manager', [
            'portfolios' => $owner->portfolios()->get()
                ->map(fn ($p) => ['portfolio' => $p, 'summary' => $service->summary($p)]),
            'parcelRows' => $service->parcelsWithAssignment($owner),
        ]);
    }

    private function owner(): Owner
    {
        return Owner::findOrFail($this->ownerId);
    }
}
