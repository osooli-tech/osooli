<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeedStatus;
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
}
