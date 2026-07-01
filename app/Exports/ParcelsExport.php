<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Parcel>
 */
class ParcelsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly string $search = '',
        private readonly string $assetType = '',
        private readonly string $landTransaction = '',
        private readonly string $deedStatus = '',
    ) {}

    /** @return Builder<Parcel> */
    public function query(): Builder
    {
        return Parcel::query()
            ->with(['plan.district', 'latestDeed'])
            ->filtered($this->search, $this->assetType, $this->landTransaction, $this->deedStatus)
            ->orderBy('parcel_no');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            __('parcels.parcel_no'),
            __('parcels.plan_no'),
            __('parcels.district'),
            __('parcels.asset_type'),
            __('parcels.land_transaction'),
            __('parcels.deed_no'),
            __('parcels.deed_date'),
            __('parcels.area_deed'),
            __('parcels.deed_status'),
            __('parcels.deed_class'),
        ];
    }

    /** @return array<int, string> */
    public function map($parcel): array
    {
        $plan = $parcel->plan;
        $district = $plan !== null ? $plan->district : null;
        $deed = $parcel->latestDeed;
        $districtName = $district !== null
            ? (app()->isLocale('ar') ? $district->name_ar : $district->name_en)
            : null;

        return [
            $parcel->parcel_no ?? '—',
            $plan !== null ? $plan->plan_no : '—',
            $districtName ?? '—',
            $parcel->asset_type ? __('parcels.asset_types.'.$parcel->asset_type) : '—',
            $parcel->land_transaction ? __('parcels.land_transactions.'.$parcel->land_transaction) : '—',
            $deed !== null ? ($deed->deed_no ?? '—') : '—',
            $deed !== null ? ($deed->deed_date_hijri ?? '—') : '—',
            $deed !== null ? ($deed->deed_area ?? '—') : '—',
            $deed !== null && $deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—',
            $deed !== null ? ($deed->deed_class ?? '—') : '—',
        ];
    }
}
