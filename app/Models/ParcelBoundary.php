<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelBoundary extends Model
{
    protected $table = 'parcel_boundaries';

    protected $fillable = [
        'parcel_id',
        'n_border',
        's_border',
        'e_border',
        'w_border',
        'n_dim',
        's_dim',
        'e_dim',
        'w_dim',
        'measured_area',
        'survey_date',
        'engineering_office_id',
    ];

    protected function casts(): array
    {
        return [
            'n_dim' => 'decimal:2',
            's_dim' => 'decimal:2',
            'e_dim' => 'decimal:2',
            'w_dim' => 'decimal:2',
            'measured_area' => 'decimal:2',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function engineeringOffice(): BelongsTo
    {
        return $this->belongsTo(EngineeringOffice::class);
    }
}
