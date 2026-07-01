<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeedStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $parcel_no
 * @property string|null $geo_id
 * @property string|null $asset_type
 * @property string|null $land_transaction
 * @property string|null $allocation_method
 * @property string|null $fall_in
 * @property string|null $source_gdb_id
 */
class Parcel extends Model
{
    protected $fillable = [
        'parcel_no',
        'geo_id',
        'plan_id',
        'parent_parcel_id',
        'geom',
        'asset_type',
        'land_transaction',
        'allocation_method',
        'fall_in',
        'source_gdb_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // The parent building/land this sub-unit (apartment) belongs to
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parent_parcel_id');
    }

    // Sub-units (apartments) contained within this parcel
    public function subUnits(): HasMany
    {
        return $this->hasMany(Parcel::class, 'parent_parcel_id');
    }

    public function deeds(): HasMany
    {
        return $this->hasMany(Deed::class);
    }

    // Most recent deed regardless of status — used in list views
    /** @return HasOne<Deed, $this> */
    public function latestDeed(): HasOne
    {
        return $this->hasOne(Deed::class)->latestOfMany();
    }

    // Active deed only (status = Updated) — used where status filtering matters
    public function currentDeed(): HasOne
    {
        return $this->hasOne(Deed::class)->where('deed_status', DeedStatus::Updated->value)->latestOfMany();
    }

    public function boundary(): HasOne
    {
        return $this->hasOne(ParcelBoundary::class);
    }

    public function surveyDecisions(): HasMany
    {
        return $this->hasMany(SurveyDecision::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ParcelPhoto::class);
    }

    public function modificationRequests(): HasMany
    {
        return $this->hasMany(ModificationRequest::class);
    }

    /**
     * Shared search/filter logic — used by the parcels list page and both export formats.
     *
     * @param  Builder<Parcel>  $query
     * @return Builder<Parcel>
     */
    public function scopeFiltered(
        Builder $query,
        string $search = '',
        string $assetType = '',
        string $landTransaction = '',
        string $deedStatus = ''
    ): Builder {
        return $query
            ->when($search !== '', function (Builder $q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('parcel_no', 'ilike', $term)
                        ->orWhereHas('deeds', fn (Builder $d) => $d->where('deed_no', 'ilike', $term));
                });
            })
            ->when($assetType !== '', fn (Builder $q) => $q->where('asset_type', $assetType))
            ->when($landTransaction !== '', fn (Builder $q) => $q->where('land_transaction', $landTransaction))
            ->when($deedStatus !== '', fn (Builder $q) => $q->whereHas('deeds', fn (Builder $d) => $d->where('deed_status', $deedStatus)));
    }
}
