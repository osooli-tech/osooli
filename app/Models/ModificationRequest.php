<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModificationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int                       $id
 * @property int                       $parcel_id
 * @property int                       $requested_by
 * @property string                    $field_name
 * @property string|null               $old_value
 * @property string|null               $new_value
 * @property ModificationRequestStatus $status
 * @property string|null               $notes
 * @property Carbon|null               $resolved_at
 * @property Carbon                    $created_at
 * @property Carbon                    $updated_at
 */
class ModificationRequest extends Model
{
    protected $table = 'modification_requests';

    protected $fillable = [
        'parcel_id',
        'requested_by',
        'field_name',
        'old_value',
        'new_value',
        'status',
        'notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ModificationRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'requested_by');
    }
}
