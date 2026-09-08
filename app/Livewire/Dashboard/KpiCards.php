<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\DeedOwner;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class KpiCards extends Component
{
    /**
     * Which tiles to render. Empty shows every tile.
     *
     * The page mounts this twice: a short headline strip above the map, and
     * the remainder below it, so the map is not pushed under a wall of numbers.
     *
     * @var list<string>
     */
    public array $only = [];

    /** @var list<string> */
    public array $except = [];

    public function shows(string $key): bool
    {
        if ($this->only !== []) {
            return in_array($key, $this->only, true);
        }

        return ! in_array($key, $this->except, true);
    }

    public int $totalParcels = 0;

    public int $totalDeeds = 0;

    public int $totalPlans = 0;

    public int $totalOwners = 0;

    public int $multiOwnerDeeds = 0;

    public int $pendingRequests = 0;

    public int $updatedDeeds = 0;

    public int $nonUpdatedDeeds = 0;

    public int $activeAlerts = 0;

    public string $totalArea = '—';

    public string $avgArea = '—';

    public string $maxArea = '—';

    public string $minArea = '—';

    /** Name of the owner who holds the most deeds */
    public string $topOwnerName = '—';

    /** Number of deeds held by the top owner */
    public int $topOwnerDeedCount = 0;

    public string $avgPricePerMetre = '—';

    public string $totalEstimatedValue = '—';

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $ownerIds = OwnerScope::ownerIds($user);
        $parcelIds = OwnerScope::parcelIds($user);
        $restricted = $parcelIds !== null;

        $this->totalParcels = Parcel::query()
            ->when($restricted, fn ($q) => $q->whereIn('id', $parcelIds))
            ->count();

        $this->totalPlans = Plan::query()
            ->when(
                $restricted,
                fn ($q) => $q->whereHas('parcels', fn ($p) => $p->whereIn('id', $parcelIds))
            )
            ->count();

        $this->pendingRequests = ModificationRequest::query()
            ->where('status', 'pending')
            ->when(
                $restricted,
                fn ($q) => $q->whereHas('parcel', fn ($p) => $p->whereIn('id', $parcelIds))
            )
            ->count();

        // Distinct owners that actually appear in deed_owners
        $this->totalOwners = DeedOwner::query()
            ->when($restricted, fn ($q) => $q->whereIn('owner_id', $ownerIds))
            ->distinct('owner_id')
            ->count('owner_id');

        $deeds = Deed::query()->when($restricted, fn ($q) => $q->whereIn('parcel_id', $parcelIds));
        $this->totalDeeds = (clone $deeds)->count();
        $this->updatedDeeds = (clone $deeds)->where('deed_status', DeedStatus::Updated->value)->count();
        $this->nonUpdatedDeeds = (clone $deeds)->where('deed_status', DeedStatus::Old->value)->count();
        $this->activeAlerts = $this->pendingRequests + $this->nonUpdatedDeeds;

        // Deeds with more than one owner — COUNT computed in the database
        // over a small grouped subquery, not pulled into PHP to be counted there.
        $multiOwnerSub = DeedOwner::query()
            ->when($restricted, fn ($q) => $q->whereIn('owner_id', $ownerIds))
            ->select('deed_id')
            ->groupBy('deed_id')
            ->havingRaw('COUNT(*) > 1');
        $this->multiOwnerDeeds = DB::query()->fromSub($multiOwnerSub, 'sub')->count();

        // All four area aggregates in one query
        $area = (clone $deeds)
            ->selectRaw('
                COALESCE(SUM(deed_area), 0) AS total,
                COALESCE(AVG(deed_area), 0) AS avg,
                COALESCE(MAX(deed_area), 0) AS max_v,
                COALESCE(MIN(deed_area), 0) AS min_v
            ')
            ->first();

        $this->totalArea = $this->fmtArea((float) ($area?->getAttribute('total') ?? 0));
        $this->avgArea = $this->fmtArea((float) ($area?->getAttribute('avg') ?? 0));
        $this->maxArea = $this->fmtArea((float) ($area?->getAttribute('max_v') ?? 0));
        $this->minArea = $this->fmtArea((float) ($area?->getAttribute('min_v') ?? 0));

        // Owner with the most deeds (one JOIN query, no N+1)
        $top = Owner::query()
            ->join('deed_owners', 'deed_owners.owner_id', '=', 'owners.id')
            ->when($restricted, fn ($q) => $q->whereIn('owners.id', $ownerIds))
            ->selectRaw('owners.name AS name, COUNT(deed_owners.deed_id) AS deed_cnt')
            ->groupBy('owners.id', 'owners.name')
            ->orderByDesc('deed_cnt')
            ->first();

        if ($top !== null) {
            $this->topOwnerName = (string) $top->getAttribute('name');
            $this->topOwnerDeedCount = (int) $top->getAttribute('deed_cnt');
        }

        // AVG/SUM ignore NULL rows on their own — a parcel without a
        // recorded price just does not count toward either figure.
        $pricing = Parcel::query()
            ->when($restricted, fn ($q) => $q->whereIn('id', $parcelIds))
            ->selectRaw('AVG(m_price) AS avg_price, SUM(parcel_price) AS total_value')
            ->first();

        $avgPrice = $pricing?->getAttribute('avg_price');
        $totalValue = $pricing?->getAttribute('total_value');

        $this->avgPricePerMetre = $avgPrice === null
            ? '—'
            : number_format((float) $avgPrice, 0).' '.__('parcels.sar');

        $this->totalEstimatedValue = $totalValue === null
            ? '—'
            : number_format((float) $totalValue, 0).' '.__('parcels.sar');
    }

    private function fmtArea(float $v): string
    {
        if ($v <= 0.0) {
            return '—';
        }

        return $v >= 1_000_000
            ? number_format($v / 1_000_000, 2).' '.__('dashboard.area_unit_million')
            : number_format($v, 0).' '.__('dashboard.area_unit_sqm');
    }

    public function render(): View
    {
        return view('livewire.dashboard.kpi-cards');
    }
}
