<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property ImportKind $kind
 * @property ImportStatus $status
 * @property array<string, mixed>|null $preview
 * @property array<string, mixed>|null $result
 */
class ImportBatch extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'kind', 'status', 'original_filename',
        'byte_size', 'received_chunks', 'checksum', 'stored_path',
        'preview', 'result', 'error_message', 'analyzed_at', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ImportKind::class,
            'status' => ImportStatus::class,
            'preview' => 'array',
            'result' => 'array',
            'analyzed_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Moves the batch forward, refusing any transition the lifecycle forbids.
     *
     * Returns false rather than throwing: a double-clicked Confirm is a normal
     * thing for a browser to do, not an exceptional one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(ImportStatus $status, array $attributes = []): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->forceFill([...$attributes, 'status' => $status])->save();

        return true;
    }

    public function markFailed(string $message): void
    {
        $this->forceFill(['status' => ImportStatus::Failed, 'error_message' => $message])->save();
    }

    /**
     * @param  Builder<ImportBatch>  $query
     * @return Builder<ImportBatch>
     */
    public function scopeStale(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days))
            ->whereNotNull('stored_path');
    }
}
