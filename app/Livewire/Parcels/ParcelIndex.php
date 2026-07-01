<?php

declare(strict_types=1);

namespace App\Livewire\Parcels;

use App\Enums\AssetType;
use App\Enums\DeedStatus;
use App\Enums\LandTransaction;
use App\Models\Parcel;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class ParcelIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterAssetType = '';

    public string $filterLandTransaction = '';

    public string $filterDeedStatus = '';

    public bool $showAllColumns = false;

    /** @var array<string, string> */
    public array $assetTypeOptions = [];

    /** @var array<string, string> */
    public array $landTransactionOptions = [];

    /** @var array<string, string> */
    public array $deedStatusOptions = [];

    public function mount(): void
    {
        $this->assetTypeOptions = array_column(
            array_map(fn (AssetType $e) => ['value' => $e->value, 'label' => __('parcels.asset_types.'.$e->value)], AssetType::cases()),
            'label',
            'value'
        );

        $this->landTransactionOptions = array_column(
            array_map(fn (LandTransaction $e) => ['value' => $e->value, 'label' => __('parcels.land_transactions.'.$e->value)], LandTransaction::cases()),
            'label',
            'value'
        );

        $this->deedStatusOptions = array_column(
            array_map(fn (DeedStatus $e) => ['value' => $e->value, 'label' => __('parcels.deed_statuses.'.$e->value)], DeedStatus::cases()),
            'label',
            'value'
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAssetType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLandTransaction(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDeedStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterAssetType', 'filterLandTransaction', 'filterDeedStatus']);
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<Parcel> */
    public function parcels(): LengthAwarePaginator
    {
        return Parcel::query()
            ->with(['plan.district', 'latestDeed'])
            ->filtered($this->search, $this->filterAssetType, $this->filterLandTransaction, $this->filterDeedStatus)
            ->orderBy('parcel_no')
            ->paginate(25);
    }

    /**
     * Which optional columns have at least one non-null value on the current page.
     *
     * @param  Collection<int, Parcel>  $items
     * @return array<string, bool>
     */
    private function populatedColumns(Collection $items): array
    {
        $deeds = $items->pluck('latestDeed');

        return [
            'asset_type' => $items->pluck('asset_type')->filter()->isNotEmpty(),
            'land_transaction' => $items->pluck('land_transaction')->filter()->isNotEmpty(),
            'district' => $items->pluck('plan.district')->filter()->isNotEmpty(),
            'deed_no' => $deeds->pluck('deed_no')->filter()->isNotEmpty(),
            'deed_date' => $deeds->pluck('deed_date_hijri')->filter()->isNotEmpty(),
            'deed_area' => $deeds->pluck('deed_area')->filter()->isNotEmpty(),
            'deed_status' => $deeds->pluck('deed_status')->filter()->isNotEmpty(),
            'deed_class' => $deeds->pluck('deed_class')->filter()->isNotEmpty(),
        ];
    }

    public function render(): View
    {
        $parcels = $this->parcels();

        /** @var Collection<int, Parcel> $items */
        $items = $parcels->getCollection();

        return view('livewire.parcels.parcel-index', [
            'parcels' => $parcels,
            'populated' => $this->populatedColumns($items),
        ]);
    }
}
