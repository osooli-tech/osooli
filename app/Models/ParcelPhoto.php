<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelPhoto extends Model
{
    protected $table = 'parcel_photos';

    protected $fillable = [
        'parcel_id',
        'photo_url',
        'photo_type',
    ];

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
