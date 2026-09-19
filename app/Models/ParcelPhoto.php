<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhotoType;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $parcel_id
 * @property int|null $deed_id
 * @property string $photo_url
 * @property PhotoType|null $photo_type
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $storage_disk
 * @property int|null $uploaded_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ParcelPhoto extends Model
{
    public const STATUS_PENDING = 'معلق';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_REJECTED = 'مرفوض';

    /** Disk that every newly uploaded document is written to. */
    public const PRIVATE_DISK = 'documents';

    /**
     * Disk the rows written before the upload feature point at: files an
     * administrator dropped into storage/app/public and linked with artisan.
     */
    public const LEGACY_DISK = 'public';

    /** Name of the global scope that hides documents still under review. */
    private const REVIEW_SCOPE = 'reviewed_documents';

    protected $table = 'parcel_photos';

    protected $fillable = [
        'parcel_id',
        'deed_id',
        'photo_url',
        'photo_type',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'status',
    ];

    /**
     * A document that has not been approved is invisible to every ordinary
     * read — the documents list, the parcel page, the print report and the
     * mobile API all go through Eloquent and none of them asks about status.
     *
     * A global scope is what covers all of them at once: the alternative is a
     * `where` at each call site, and the one that gets forgotten is the one
     * that leaks an unreviewed deed scan to an owner's phone. Screens that are
     * meant to see everything ask for it by name through scopeForReview().
     */
    protected static function booted(): void
    {
        static::addGlobalScope(self::REVIEW_SCOPE, function (Builder $query): void {
            if (self::viewerMayReview()) {
                return;
            }

            $query->where($query->qualifyColumn('status'), self::STATUS_APPROVED);
        });
    }

    protected function casts(): array
    {
        return [
            'photo_type' => PhotoType::class,
            'size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /**
     * Only set on a Deed-type photo — which specific deed record this scan documents.
     *
     * @return BelongsTo<Deed, $this>
     */
    public function deed(): BelongsTo
    {
        return $this->belongsTo(Deed::class);
    }

    /**
     * Which disk holds this document's file, and where on it.
     *
     * The single place the private/public split is decided. Rows created
     * before the upload feature carry `photo_url` as a public URL path
     * ('/storage/deeds/123.pdf') and no `storage_disk`; their files are still
     * in storage/app/public and must keep working untouched. Rows created by
     * an upload carry a plain relative path and name their disk. Nothing else
     * in the application may re-derive this.
     *
     * @return array{disk: string, path: string}
     */
    public function storageLocation(): array
    {
        if ($this->storage_disk !== null && $this->storage_disk !== '') {
            return ['disk' => $this->storage_disk, 'path' => ltrim($this->photo_url, '/')];
        }

        // '/storage/x/y.pdf' and 'storage/x/y.pdf' both mean 'x/y.pdf' on the
        // public disk, because '/storage' is the symlink to that disk's root.
        $path = ltrim($this->photo_url, '/');
        $path = Str::startsWith($path, 'storage/') ? Str::after($path, 'storage/') : $path;

        return ['disk' => self::LEGACY_DISK, 'path' => $path];
    }

    /**
     * Filename the browser should save the download under: what the uploader
     * called it, falling back to the stored name for rows that predate it.
     */
    public function downloadName(): string
    {
        $name = trim((string) $this->original_name);

        return $name !== '' ? $name : basename($this->storageLocation()['path']);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Documents of every status, for the review screens.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForReview(Builder $query, ?string $status = null): void
    {
        $query->withoutGlobalScope(self::REVIEW_SCOPE)
            ->when($status !== null, fn (Builder $q) => $q->where($q->qualifyColumn('status'), $status));
    }

    /**
     * Documents still waiting for a decision.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->forReview(self::STATUS_PENDING);
    }

    /**
     * Whether the current viewer is allowed to see documents under review.
     *
     * Console runs are exempt from the scope entirely: the four `link:*`
     * commands reconcile rows with updateOrCreate(), and a hidden row would
     * make them insert a duplicate instead of updating the one that is there.
     *
     * The mobile API authenticates an Owner, which is Authorizable but carries
     * no roles, so can() resolves to false for it — an owner never sees a
     * document that has not been approved.
     */
    private static function viewerMayReview(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        $user = auth()->user();

        return $user instanceof Authorizable && $user->can('documents.review');
    }
}
