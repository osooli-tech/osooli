<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Archiving for a core record: parcels, deeds and owners.
 *
 * SoftDeletes supplies the mechanism; this adds the part it leaves out —
 * recording who archived the row. An audit entry says an archive happened, but
 * answering "why is this parcel not in the list?" should not require going to
 * the audit log, so the answer sits on the row itself.
 *
 * The word used throughout the interface is "archive", never "delete", because
 * the row and everything hanging off it stay exactly where they were.
 *
 * @property Carbon|null $deleted_at
 * @property int|null $archived_by
 */
trait Archivable
{
    use SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isArchived(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Archive the record, stamping the current user onto it.
     *
     * `archived_by` is written before the delete and saved quietly, so the
     * stamp lands without firing another round of model events on a row that
     * is on its way out.
     */
    public function archive(): bool
    {
        $this->archived_by = Auth::id();
        $this->saveQuietly();

        return (bool) $this->delete();
    }

    /**
     * Bring the record back and clear the stamp, so a row restored and later
     * archived again does not carry the earlier person's name.
     */
    public function unarchive(): bool
    {
        $restored = (bool) $this->restore();

        if ($restored) {
            $this->archived_by = null;
            $this->saveQuietly();
        }

        return $restored;
    }

    /** @param  Builder<static>  $query */
    public function scopeArchived(Builder $query): void
    {
        $query->onlyTrashed();
    }
}
