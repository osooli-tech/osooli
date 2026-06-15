<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
