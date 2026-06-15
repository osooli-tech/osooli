<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
            'sync_started_at'  => 'datetime',
            'sync_finished_at' => 'datetime',
            'created_at'       => 'datetime',
        ];
    }
}
