<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One portal account's grant on one owner.
 *
 * A grant waiting for review (needs_review) opens nothing until a member of
 * staff approves it. Written only through PortalAccessService.
 *
 * @property int $id
 * @property int $portal_account_id
 * @property int $owner_id
 * @property PortalRelation $relation
 * @property bool $can_manage
 * @property PortalGrantSource $source
 * @property bool $needs_review
 * @property int|null $granted_by
 */
class PortalAccountOwner extends Model
{
    protected $fillable = ['portal_account_id', 'owner_id', 'relation', 'can_manage', 'source', 'needs_review', 'granted_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relation' => PortalRelation::class,
            'source' => PortalGrantSource::class,
            'can_manage' => 'boolean',
            'needs_review' => 'boolean',
        ];
    }

    /** @return BelongsTo<PortalAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PortalAccount::class, 'portal_account_id');
    }

    /** @return BelongsTo<Owner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isSelf(): bool
    {
        return $this->relation === PortalRelation::Self_;
    }

    /** Approved, and on an owner not archived. */
    public function isUsable(): bool
    {
        return ! $this->needs_review && $this->owner()->exists();
    }
}
