<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Models\Deed;
use App\Models\Parcel;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * The filters of the export page, turned into a query over deeds.
 *
 * One export row is one deed, so every filter narrows deeds: directly
 * (number, status, area…), through the parcel the deed is on (location,
 * asset type, price, polygon…), or through its owners. The same query feeds
 * the live count on the page and the export itself, so the number shown
 * before exporting is the number of features in the file.
 */
final class DeedExportFilters
{
    /** Every key the page may send, with its empty value. */
    public const DEFAULTS = [
        'region_id' => '',
        'city_id' => '',
        'district_id' => '',
        'plan_no' => '',
        'parcel' => '',
        'deed_no' => '',
        'owner' => '',
        'deed_status' => [],
        'deed_class' => [],
        'asset_type' => [],
        'fall_in' => [],
        'land_transaction' => [],
        'allocation_method' => [],
        'area_min' => '',
        'area_max' => '',
        'price_min' => '',
        'price_max' => '',
        'date_from' => '',
        'date_to' => '',
        'has_geometry' => '',
        'has_boundary' => '',
        'has_survey' => '',
        'has_documents' => '',
        'include_archived' => false,
        'include_deedless' => true,
    ];

    /** Filters that only a deed can meet; set, they rule deedless parcels out. */
    private const DEED_ONLY = ['deed_no', 'owner', 'deed_status', 'deed_class', 'area_min', 'area_max', 'date_from', 'date_to'];

    /** Multi-select filters on the parcel, by column. */
    private const PARCEL_LISTS = ['asset_type', 'fall_in', 'land_transaction', 'allocation_method'];

    /** @var array<string, mixed> */
    private array $values;

    /** @param  array<string, mixed>  $values */
    public function __construct(array $values)
    {
        $this->values = array_merge(self::DEFAULTS, array_intersect_key($values, self::DEFAULTS));
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->values;
    }

    /** The filters actually set, for the export's own record of what it holds. */
    public function active(): array
    {
        return array_filter($this->values, static fn (mixed $v): bool => $v !== '' && $v !== [] && $v !== false && $v !== null);
    }

    public function includeArchived(): bool
    {
        return (bool) $this->values['include_archived'];
    }

    /** How many features the export will hold: deeds, plus deedless parcels. */
    public function count(?User $user): int
    {
        return $this->query($user)->count() + ($this->parcelsWithoutDeeds($user)?->count() ?? 0);
    }

    /**
     * Parcels with no deed, which a deed-by-deed export would otherwise
     * leave out entirely — or null when they cannot belong: a filter on a
     * deed's own fields is set, or the user sees parcels only through the
     * owners of their deeds.
     *
     * @return Builder<Parcel>|null
     */
    public function parcelsWithoutDeeds(?User $user): ?Builder
    {
        $deedFilterSet = array_filter(
            array_intersect_key($this->values, array_flip(self::DEED_ONLY)),
            static fn (mixed $v): bool => $v !== '' && $v !== []
        ) !== [];

        if (! $this->values['include_deedless'] || $deedFilterSet || OwnerScope::isRestricted($user)) {
            return null;
        }

        $archived = $this->includeArchived();

        return Parcel::query()
            ->when($archived, fn (Builder $q) => $q->withTrashed())
            ->whereDoesntHave('deeds', fn ($d) => $archived ? $d->withoutGlobalScope(SoftDeletingScope::class) : $d)
            ->tap(fn (Builder $p) => $this->parcelConditions($p));
    }

    /**
     * Deeds matching the filters that `$user` is allowed to see.
     *
     * @return Builder<Deed>
     */
    public function query(?User $user): Builder
    {
        $v = $this->values;
        $archived = $this->includeArchived();

        $query = Deed::query()->when($archived, fn (Builder $q) => $q->withTrashed());

        // A user restricted to certain owners exports only their parcels.
        $visible = OwnerScope::parcelIds($user);
        if ($visible !== null) {
            $query->whereIn('parcel_id', $visible);
        }

        $query->when($v['deed_no'] !== '', fn (Builder $q) => $q->whereLike('deed_no', '%'.trim((string) $v['deed_no']).'%'))
            ->when($v['deed_status'] !== [], fn (Builder $q) => $q->whereIn('deed_status', (array) $v['deed_status']))
            ->when($v['deed_class'] !== [], fn (Builder $q) => $q->whereIn('deed_class', (array) $v['deed_class']))
            ->when(is_numeric($v['area_min']), fn (Builder $q) => $q->where('deed_area', '>=', (float) $v['area_min']))
            ->when(is_numeric($v['area_max']), fn (Builder $q) => $q->where('deed_area', '<=', (float) $v['area_max']))
            // Hijri dates are stored as 'YYYY-MM-DD' text, which sorts correctly as text.
            ->when($v['date_from'] !== '', fn (Builder $q) => $q->where('deed_date_hijri', '>=', (string) $v['date_from']))
            ->when($v['date_to'] !== '', fn (Builder $q) => $q->where('deed_date_hijri', '<=', (string) $v['date_to']))
            ->when($v['owner'] !== '', function (Builder $q) use ($v, $archived): void {
                $term = '%'.trim((string) $v['owner']).'%';
                $q->whereHas('owners', fn ($o) => $o
                    ->when($archived, fn ($x) => $x->withoutGlobalScope(SoftDeletingScope::class))
                    ->where(fn (Builder $x) => $x->whereLike('name', $term)
                        ->orWhereLike('national_id', $term)
                        ->orWhereLike('phone', $term)));
            });

        $query->whereHas('parcel', function ($p) use ($archived): void {
            // withTrashed(), spelled as the scope it removes: inside whereHas the
            // builder is typed by relation, and only the scope call is on it.
            $p->when($archived, fn ($q) => $q->withoutGlobalScope(SoftDeletingScope::class));
            $this->parcelConditions($p);
        });

        return $query;
    }

    /**
     * The filters that describe the land: location, plan, parcel number,
     * classifications, price, and which records it has.
     */
    private function parcelConditions(mixed $p): void
    {
        $v = $this->values;

        if ($v['district_id'] !== '') {
            $p->whereHas('plan', fn (Builder $q) => $q->where('district_id', (int) $v['district_id']));
        } elseif ($v['city_id'] !== '') {
            $p->whereHas('plan.district', fn (Builder $q) => $q->where('city_id', (int) $v['city_id']));
        } elseif ($v['region_id'] !== '') {
            $p->whereHas('plan.district.city', fn (Builder $q) => $q->where('region_id', (int) $v['region_id']));
        }

        if ($v['plan_no'] !== '') {
            $p->whereHas('plan', fn (Builder $q) => $q->whereLike('plan_no', '%'.trim((string) $v['plan_no']).'%'));
        }

        if ($v['parcel'] !== '') {
            $term = '%'.trim((string) $v['parcel']).'%';
            $p->where(fn (Builder $q) => $q->whereLike('parcel_no', $term)->orWhereLike('geo_id', $term));
        }

        foreach (self::PARCEL_LISTS as $column) {
            if ($v[$column] !== []) {
                $p->whereIn($column, (array) $v[$column]);
            }
        }

        $p->when(is_numeric($v['price_min']), fn (Builder $q) => $q->where('m_price', '>=', (float) $v['price_min']))
            ->when(is_numeric($v['price_max']), fn (Builder $q) => $q->where('m_price', '<=', (float) $v['price_max']));

        self::presence($p, $v['has_geometry'], fn (Builder $q) => $q->whereNotNull('geom'), fn (Builder $q) => $q->whereNull('geom'));
        self::presence($p, $v['has_boundary'], fn (Builder $q) => $q->has('boundary'), fn (Builder $q) => $q->doesntHave('boundary'));
        self::presence($p, $v['has_survey'], fn (Builder $q) => $q->has('surveyDecisions'), fn (Builder $q) => $q->doesntHave('surveyDecisions'));
        self::presence($p, $v['has_documents'], fn (Builder $q) => $q->has('photos'), fn (Builder $q) => $q->doesntHave('photos'));
    }

    /** Apply a yes / no / any filter. */
    private static function presence(Builder $query, mixed $choice, callable $yes, callable $no): void
    {
        match ($choice) {
            'yes' => $yes($query),
            'no' => $no($query),
            default => null,
        };
    }
}
