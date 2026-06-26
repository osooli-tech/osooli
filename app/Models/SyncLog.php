<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $sync_started_at
 * @property Carbon|null $sync_finished_at
 * @property Carbon $created_at
 */
class SyncLog extends Model
{
    protected $table = 'sync_log';

    // Append-only — no updates ever happen to sync log rows
    public $timestamps = false;

    protected $fillable = [
        'sync_started_at',
        'sync_finished_at',
        'records_imported',
        'records_updated',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sync_started_at' => 'datetime',
            'sync_finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
