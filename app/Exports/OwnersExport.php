<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Owner>
 */
class OwnersExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly string $search = '') {}

    /** @return Builder<Owner> */
    public function query(): Builder
    {
        return Owner::query()
            ->withCount(['deeds as parcel_count' => fn ($q) => $q->selectRaw('count(distinct parcel_id)')])
            ->withCount('deeds')
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'ilike', $term)
                        ->orWhere('national_id', 'ilike', $term)
                        ->orWhere('phone', 'ilike', $term);
                });
            })
            ->orderBy('name');
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            __('owners.name'),
            __('owners.national_id'),
            __('owners.phone'),
            __('owners.email'),
            __('owners.parcel_count'),
            __('owners.deed_count'),
        ];
    }

    /** @return array<int, string> */
    public function map($owner): array
    {
        return [
            $owner->name,
            $owner->national_id ?? '—',
            $owner->phone ?? '—',
            $owner->email ?? '—',
            // A custom withCount() alias, not the plain "{relation}_count"
            // convention Larastan recognises on the model automatically.
            (string) $owner->getAttribute('parcel_count'),
            (string) $owner->deeds_count,
        ];
    }
}
