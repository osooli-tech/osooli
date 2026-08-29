<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\AssetType;
use App\Enums\DeedStatus;
use App\Enums\QrarSource;
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

    /** @var array<string, int> parcels per engineering office */
    public array $byEngineeringOffice = [];

    /** @var array<string, int> */
    public array $byQrarSource = [];

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

        // 7 — By the authority that issued the survey decision
        $sourceDefaults = array_fill_keys(
            array_map(fn (QrarSource $e) => $e->value, QrarSource::cases()),
            0
        );
        $sourceFromDb = DB::table('survey_decisions')
            ->selectRaw('qrar_source::text AS src, COUNT(*) as cnt')
            ->whereNotNull('qrar_source')
            ->groupBy('qrar_source')
            ->pluck('cnt', 'src')
            ->map(fn ($v) => (int) $v)
            ->toArray();
        $this->byQrarSource = array_merge($sourceDefaults, $sourceFromDb);
    }

    public function render(): View
    {
        return view('livewire.dashboard.distribution-charts');
    }
}
