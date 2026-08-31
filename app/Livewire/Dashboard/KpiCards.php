<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\DeedStatus;
use App\Models\ModificationRequest;
use App\Models\Parcel;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
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
        $this->totalParcels = Parcel::count();
        $this->totalPlans = Plan::count();
        $this->pendingRequests = ModificationRequest::where('status', 'pending')->count();

        // Distinct owners that actually appear in deed_owners
        $this->totalOwners = DB::table('deed_owners')->distinct('owner_id')->count('owner_id');

        $this->totalDeeds = DB::table('deeds')->count();
        $this->updatedDeeds = DB::table('deeds')->where('deed_status', DeedStatus::Updated->value)->count();
        $this->nonUpdatedDeeds = DB::table('deeds')->where('deed_status', DeedStatus::Old->value)->count();
        $this->activeAlerts = $this->pendingRequests + $this->nonUpdatedDeeds;

        // Deeds with more than one owner — single subquery
        $this->multiOwnerDeeds = (int) DB::scalar(
            'SELECT COUNT(*) FROM (
                SELECT deed_id FROM deed_owners GROUP BY deed_id HAVING COUNT(*) > 1
             ) sub'
        );

        // All four area aggregates in one query
        /** @var object{total: string, avg: string, max_v: string, min_v: string}|null $area */
        $area = DB::selectOne(
            'SELECT COALESCE(SUM(deed_area), 0) AS total,
                    COALESCE(AVG(deed_area), 0) AS avg,
                    COALESCE(MAX(deed_area), 0) AS max_v,
                    COALESCE(MIN(deed_area), 0) AS min_v
             FROM deeds'
        );

        $this->totalArea = $this->fmtArea((float) ($area->total ?? 0));
        $this->avgArea = $this->fmtArea((float) ($area->avg ?? 0));
        $this->maxArea = $this->fmtArea((float) ($area->max_v ?? 0));
        $this->minArea = $this->fmtArea((float) ($area->min_v ?? 0));

        // Owner with the most deeds (one JOIN query, no N+1)
        /** @var object{name: string, deed_cnt: int}|null $top */
        $top = DB::selectOne(
            'SELECT o.name, COUNT(dw.deed_id) AS deed_cnt
             FROM deed_owners dw
             JOIN owners o ON dw.owner_id = o.id
             GROUP BY o.id, o.name
             ORDER BY deed_cnt DESC
             LIMIT 1'
        );

        if ($top !== null) {
            $this->topOwnerName = $top->name;
            $this->topOwnerDeedCount = (int) $top->deed_cnt;
        }

        // AVG/SUM ignore NULL rows on their own — a parcel without a
        // recorded price just does not count toward either figure.
        /** @var object{avg_price: string|null, total_value: string|null}|null $pricing */
        $pricing = DB::selectOne('SELECT AVG(m_price) AS avg_price, SUM(parcel_price) AS total_value FROM parcels');

        $this->avgPricePerMetre = $pricing?->avg_price === null
            ? '—'
            : number_format((float) $pricing->avg_price, 0).' '.__('parcels.sar');

        $this->totalEstimatedValue = $pricing?->total_value === null
            ? '—'
            : number_format((float) $pricing->total_value, 0).' '.__('parcels.sar');
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
