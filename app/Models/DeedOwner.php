<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeedOwner extends Model
{
    protected $table = 'deed_owners';

    protected $fillable = [
        'deed_id',
        'owner_id',
        'ownership_share',
        'source_gdb_id',
    ];

    protected function casts(): array
    {
        return [
            'ownership_share' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Deed, $this> */
    public function deed(): BelongsTo
    {
        return $this->belongsTo(Deed::class);
    }

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }
}
