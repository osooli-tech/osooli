<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Owners linked to no deed at all. With no parcel there is no polygon, so
 * they cannot travel in the GeoJSON export; this spreadsheet carries them.
 *
 * @implements WithMapping<Owner>
 */
class OwnersWithoutDeedsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly bool $includeArchived = false) {}

    /** @return Builder<Owner> */
    public static function base(bool $includeArchived): Builder
    {
        return Owner::query()
            ->when($includeArchived, fn (Builder $q) => $q->withTrashed())
            // Any deed at all, archived ones included: an owner of an archived
            // deed still has a deed on record.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('deed_owners')->whereColumn('deed_owners.owner_id', 'owners.id'));
    }

    /** @return Builder<Owner> */
    public function query(): Builder
    {
        return self::base($this->includeArchived)->orderBy('name');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            __('exports_center.owner_columns.id'),
            __('owners.name'),
            __('owners.national_id'),
            __('owners.phone'),
            __('owners.email'),
            __('exports_center.owner_columns.whatsapp'),
            __('exports_center.owner_columns.created_at'),
            __('exports_center.owner_columns.archived_at'),
        ];
    }

    /** @return array<int, string|int> */
    public function map($owner): array
    {
        return [
            $owner->id,
            $owner->name,
            $owner->national_id ?? '',
            $owner->phone ?? '',
            $owner->email ?? '',
            $owner->whatsapp ?? '',
            $owner->created_at?->format('Y-m-d H:i') ?? '',
            $owner->deleted_at?->format('Y-m-d H:i') ?? '',
        ];
    }
}
