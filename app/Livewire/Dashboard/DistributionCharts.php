<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\AssetType;
use App\Enums\DeedStatus;
use App\Enums\LandTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DistributionCharts extends Component
{
    /** @var array<string, int> deed_status distribution */
    public array $byDeedStatus = [];

    /** @var array<string, int> asset_type distribution */
    public array $byAssetType = [];

    /** @var array<string, int> parcels per city (top 10) */
    public array $byCity = [];

    /** @var array<string, int> parcels per district (top 10) */
    public array $byDistrict = [];

    /** @var array<string, int> land_transaction distribution */
    public array $byLandTransaction = [];

    /** @var array<string, int> parcels per engineering office */
    public array $byEngineeringOffice = [];

    public int $linkedToDecision = 0;

    public int $notLinkedToDecision = 0;

    public function mount(): void
    {
        // 1 — Deed status (ensure both enum labels always appear)
        $deedDefaults = array_fill_keys(
            array_map(fn (DeedStatus $e) => $e->value, DeedStatus::cases()),
            0
        );
        $deedFromDb = DB::table('deeds')
            ->selectRaw('deed_status, COUNT(*) as cnt')
            ->whereNotNull('deed_status')
            ->groupBy('deed_status')
            ->pluck('cnt', 'deed_status')
            ->map(fn ($v) => (int) $v)
            ->toArray();
        $this->byDeedStatus = array_merge($deedDefaults, $deedFromDb);

        // 2 — Asset type (ensure all enum labels appear)
        $assetDefaults = array_fill_keys(
            array_map(fn (AssetType $e) => $e->value, AssetType::cases()),
            0
        );
        $assetFromDb = DB::table('parcels')
            ->selectRaw('asset_type, COUNT(*) as cnt')
            ->whereNotNull('asset_type')
            ->groupBy('asset_type')
            ->pluck('cnt', 'asset_type')
            ->map(fn ($v) => (int) $v)
            ->toArray();
        $this->byAssetType = array_merge($assetDefaults, $assetFromDb);

        // 3 — By city (parcels → plans → districts → cities)
        $this->byCity = DB::table('parcels')
            ->join('plans', 'parcels.plan_id', '=', 'plans.id')
            ->join('districts', 'plans.district_id', '=', 'districts.id')
            ->join('cities', 'districts.city_id', '=', 'cities.id')
            ->selectRaw('cities.name_ar, COUNT(parcels.id) as cnt')
            ->groupBy('cities.name_ar')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'name_ar')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // 4 — By district (parcels → plans → districts)
        $this->byDistrict = DB::table('parcels')
            ->join('plans', 'parcels.plan_id', '=', 'plans.id')
            ->join('districts', 'plans.district_id', '=', 'districts.id')
            ->selectRaw('districts.name_ar, COUNT(parcels.id) as cnt')
            ->groupBy('districts.name_ar')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'name_ar')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // 5 — Land transaction (ensure all enum labels appear)
        $txDefaults = array_fill_keys(
            array_map(fn (LandTransaction $e) => $e->value, LandTransaction::cases()),
            0
        );
        $txFromDb = DB::table('parcels')
            ->selectRaw('land_transaction, COUNT(*) as cnt')
            ->whereNotNull('land_transaction')
            ->groupBy('land_transaction')
            ->pluck('cnt', 'land_transaction')
            ->map(fn ($v) => (int) $v)
            ->toArray();
        $this->byLandTransaction = array_merge($txDefaults, $txFromDb);

        // 6 — By engineering office (parcel_boundaries → engineering_offices)
        $this->byEngineeringOffice = DB::table('parcel_boundaries')
            ->join('engineering_offices', 'parcel_boundaries.engineering_office_id', '=', 'engineering_offices.id')
            ->selectRaw('engineering_offices.name, COUNT(parcel_boundaries.parcel_id) as cnt')
            ->groupBy('engineering_offices.name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'name')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // 7 — Linked vs not linked to a survey decision
        $total = DB::table('parcels')->count();
        $linked = DB::table('survey_decisions')->distinct('parcel_id')->count('parcel_id');
        $this->linkedToDecision = (int) $linked;
        $this->notLinkedToDecision = max(0, (int) $total - (int) $linked);
    }

    public function render(): View
    {
        return view('livewire.dashboard.distribution-charts');
    }
}
