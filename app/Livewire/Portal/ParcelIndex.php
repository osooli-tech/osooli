<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Enums\AssetType;
use App\Enums\DeedStatus;
use App\Enums\LandTransaction;
use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\Owner;
use App\Models\Parcel;
use App\Queries\OwnerParcelQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The owner portal's own parcel list — same filter/column set as the
 * internal team's ParcelIndex, minus every action that edits data (this
 * portal is read-only), scoped through OwnerParcelQuery instead of
 * OwnerScope.
 */
class ParcelIndex extends Component
{
    use FiltersByCreatedAt;
    use WithPagination;

    public string $search = '';

    public string $filterAssetType = '';

    public string $filterLandTransaction = '';

    public string $filterDeedStatus = '';

    /** '' (all), 'priced', or 'unpriced'. */
    public string $filterPricing = '';

    public bool $showAllColumns = false;

    /** 'cards' shows each parcel's outline; 'table' is the dense list. */
    #[Url]
    public string $view = 'cards';

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

    public function updatingFilterPricing(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterAssetType', 'filterLandTransaction', 'filterDeedStatus', 'filterPricing', 'createdFrom', 'createdTo']);
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<Parcel> */
    public function parcels(): LengthAwarePaginator
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return (new OwnerParcelQuery($owner))
            ->filtered(['search' => $this->search ?: null, 'asset_type' => $this->filterAssetType ?: null])
            ->with(['plan.district', 'latestDeed', 'boundary'])
            ->when($this->filterLandTransaction !== '', fn (Builder $q) => $q->where('land_transaction', $this->filterLandTransaction))
            ->when($this->filterDeedStatus !== '', fn (Builder $q) => $q->whereHas(
                'latestDeed',
                fn (Builder $d) => $d->where('deed_status', $this->filterDeedStatus)
            ))
            ->when($this->filterPricing === 'priced', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner->whereNotNull('m_price')->orWhereNotNull('parcel_price')
            ))
            ->when($this->filterPricing === 'unpriced', fn (Builder $q) => $q
                ->whereNull('m_price')
                ->whereNull('parcel_price'))
            ->orderBy('parcel_no')
            ->tap(fn (Builder $q) => $this->applyCreatedAt($q, 'parcels.created_at'))
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
            'm_price' => $items->pluck('m_price')->filter()->isNotEmpty(),
            'parcel_price' => $items->pluck('parcel_price')->filter()->isNotEmpty(),
        ];
    }

    public function render(): View
    {
        $parcels = $this->parcels();

        /** @var Collection<int, Parcel> $items */
        $items = $parcels->getCollection();

        return view('livewire.portal.parcel-index', [
            'parcels' => $parcels,
            'populated' => $this->populatedColumns($items),
        ]);
    }
}
