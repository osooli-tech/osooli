<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhotoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $parcel_id
 * @property string $photo_url
 * @property PhotoType|null $photo_type
 */
class ParcelPhoto extends Model
{
    protected $table = 'parcel_photos';

    protected $fillable = [
        'parcel_id',
        'photo_url',
        'photo_type',
    ];

    protected function casts(): array
    {
        return [
            'photo_type' => PhotoType::class,
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
