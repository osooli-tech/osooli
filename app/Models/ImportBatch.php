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
     * thing for a browser to do, not an exceptional one. The in-memory legality
     * check alone is not enough to stop a genuine double commit — two requests
     * can both load the row while it is still Previewed and both pass it — so
     * the write itself is a compare-and-swap: the UPDATE only takes effect if
     * the row's status in the database still matches what this instance read.
     * A loser (0 rows affected) is treated exactly like an illegal transition,
     * and this instance is left as it was — it never adopts a status it did
     * not actually manage to write.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(ImportStatus $status, array $attributes = []): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $fill = [...$attributes, 'status' => $status, 'updated_at' => now()];

        // Cast $fill to DB-ready values on a throwaway instance rather than on
        // $this, so a failed compare-and-swap below leaves $this untouched.
        $payload = $this->newInstance()->forceFill($fill)->getAttributes();

        $affected = static::query()
            ->whereKey($this->getKey())
            ->where('status', $this->getRawOriginal('status'))
            ->update($payload);

        if ($affected === 0) {
            return false;
        }

        // Re-run the same raw $fill through $this's own cast pipeline instead
        // of reusing $payload: $payload already went through the 'array' cast
        // once (preview/result are JSON strings there), and forceFill()-ing an
        // already-encoded JSON string back through that cast would json_encode
        // it a second time, corrupting $this->preview into a double-encoded
        // string instead of the array its docblock promises.
        $this->forceFill($fill)->syncOriginal();

        return true;
    }

    /**
     * Force the batch into the terminal Failed state, recording why.
     *
     * Delegates to transitionTo(), so a batch that has already reached a
     * terminal state (Completed or Failed) is left untouched instead of being
     * retroactively flipped — see ImportStatus::allowedNext().
     */
    public function markFailed(string $message): void
    {
        $this->transitionTo(ImportStatus::Failed, ['error_message' => $message]);
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
