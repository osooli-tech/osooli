<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalGrantSource;
use App\Enums\PortalPermission;
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
 * @property array<string, bool>|null $permissions what staff turned off for this grant; null or a missing key allows
 * @property PortalGrantSource $source
 * @property bool $needs_review
 * @property int|null $granted_by
 */
class PortalAccountOwner extends Model
{
    protected $fillable = ['portal_account_id', 'owner_id', 'relation', 'can_manage', 'permissions', 'source', 'needs_review', 'granted_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'relation' => PortalRelation::class,
            'source' => PortalGrantSource::class,
            'can_manage' => 'boolean',
            'permissions' => 'array',
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

    /**
     * Whether this grant lets its account do one thing for the owner. The
     * owner's own account may always; an agent may unless staff turned it
     * off, and acting also needs can_manage. A company is an agent even on
     * an owner whose phone on record is the company's own number.
     */
    public function allows(PortalPermission $permission): bool
    {
        if ($this->isSelf() && ! (bool) $this->account?->isCompany()) {
            return true;
        }

        if ($permission->needsManage() && ! $this->can_manage) {
            return false;
        }

        return ($this->permissions[$permission->value] ?? true) !== false;
    }

    /** Approved, and on an owner not archived. */
    public function isUsable(): bool
    {
        return ! $this->needs_review && $this->owner()->exists();
    }
}
