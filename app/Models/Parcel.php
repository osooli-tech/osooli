<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    // Shortcut to the current (active) deed — status = 'محدث'
    public function currentDeed(): HasOne
    {
        return $this->hasOne(Deed::class)->where('deed_status', 'محدث')->latestOfMany();
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
