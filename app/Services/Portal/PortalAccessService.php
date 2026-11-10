<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place that changes who may open an owner in the portal.
 *
 * Every write is audited. It keeps one rule across both tables: an owner's
 * phone (owners.phone) is the phone of their own ("self") account whenever
 * they have one, so changing the phone on the staff form and approving a new
 * phone from an import land in the same place.
 */
class PortalAccessService
{
    public const AUDIT_TARGET = 'portal_account';

    /** The account for this phone, made if there is none yet. */
    public function accountForPhone(string $phone, ?string $name = null): PortalAccount
    {
        $normalised = Owner::normalisePhone($phone);

        if ($normalised === '') {
            throw new InvalidArgumentException('A portal account needs a phone number.');
        }

        $account = PortalAccount::query()->where('phone_normalized', $normalised)->first();

        if ($account !== null) {
            if ($name !== null && $account->name === null) {
                $account->update(['name' => $name]);
            }

            return $account;
        }

        return PortalAccount::create(['phone' => trim($phone), 'name' => $name]);
    }

    /**
     * Lets the account open the owner, or updates how it does.
     *
     * A grant asked for as pending never takes away one that already works.
     * A working "self" grant makes the account's phone the owner's phone and
     * ends any other account's "self" grant on that owner.
     */
    public function grant(
        PortalAccount $account,
        Owner $owner,
        PortalRelation $relation,
        bool $canManage,
        PortalGrantSource $source,
        ?User $by,
        bool $needsReview = false,
    ): PortalAccountOwner {
        return DB::transaction(function () use ($account, $owner, $relation, $canManage, $source, $by, $needsReview): PortalAccountOwner {
            $grant = PortalAccountOwner::query()
                ->where('portal_account_id', $account->id)
                ->where('owner_id', $owner->id)
                ->lockForUpdate()
                ->first();

            if ($grant !== null && ! $grant->needs_review && $needsReview) {
                return $grant;
            }

            $isNew = $grant === null;
            $grant ??= new PortalAccountOwner(['portal_account_id' => $account->id, 'owner_id' => $owner->id, 'source' => $source]);
            $grant->fill([
                'relation' => $relation,
                'can_manage' => $canManage,
                'needs_review' => $needsReview,
                'granted_by' => $by->id ?? $grant->granted_by,
            ]);

            if (! $isNew && ! $grant->isDirty()) {
                return $grant;
            }

            $grant->save();

            if (! $needsReview && $relation === PortalRelation::Self_) {
                $this->makeOwnPhone($account, $owner, $by);
            }

            $this->audit($isNew ? ($needsReview ? 'portal_grant_pending' : 'portal_grant_added') : 'portal_grant_changed', $account, $owner, $by);

            return $grant;
        });
    }

    public function revoke(PortalAccountOwner $grant, ?User $by): void
    {
        $account = $grant->account;
        $owner = Owner::withTrashed()->find($grant->owner_id);
        $grant->delete();

        $this->audit('portal_grant_revoked', $account, $owner, $by);
    }

    /** Lets a grant that was waiting for review open its owner. */
    public function approve(PortalAccountOwner $grant, User $by): PortalAccountOwner
    {
        return $this->grant($grant->account, $grant->owner, $grant->relation, $grant->can_manage, $grant->source, $by);
    }

    public function setManage(PortalAccountOwner $grant, bool $canManage, User $by): void
    {
        $grant->update(['can_manage' => $canManage]);

        $this->audit('portal_grant_manage_changed', $grant->account, $grant->owner, $by);
    }

    /**
     * What saving a phone on the owner means: their own account becomes the
     * one for the new phone. No phone leaves them without one. Accounts
     * acting for them are left as they are.
     */
    public function changeOwnerPhone(Owner $owner, ?string $phone, ?User $by): void
    {
        $phone = $phone === null ? '' : trim($phone);

        DB::transaction(function () use ($owner, $phone, $by): void {
            if ($phone === '' || Owner::normalisePhone($phone) === '') {
                foreach ($this->selfGrants($owner) as $grant) {
                    $this->revoke($grant, $by);
                }

                $owner->forceFill(['phone' => null])->save();

                return;
            }

            $current = $this->selfGrants($owner)->first();
            if ($current !== null && $current->account->phone_normalized === Owner::normalisePhone($phone)) {
                $owner->forceFill(['phone' => $phone])->save();

                return;
            }

            $this->grant($this->accountForPhone($phone), $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $by);
            $owner->forceFill(['phone' => $phone])->save();
        });
    }

    public function rename(PortalAccount $account, ?string $name, User $by): void
    {
        $name = $name === null ? null : (trim($name) ?: null);
        $account->update(['name' => $name]);

        $this->audit('portal_account_renamed', $account, null, $by);
    }

    public function deactivate(PortalAccount $account, User $by): void
    {
        $account->update(['is_active' => false]);
        $this->endSessions($account);

        $this->audit('portal_account_deactivated', $account, null, $by);
    }

    public function activate(PortalAccount $account, User $by): void
    {
        $account->update(['is_active' => true]);

        $this->audit('portal_account_activated', $account, null, $by);
    }

    /** Ends the account's web sessions and app tokens on their next request. */
    public function signOutEverywhere(PortalAccount $account, User $by): void
    {
        $this->endSessions($account);

        $this->audit('portal_account_signed_out', $account, null, $by);
    }

    /**
     * The owners this phone already reaches: by their own phone, or through
     * an account's grant. For warning before a phone is shared.
     *
     * @return Collection<int, Owner>
     */
    public function ownersSharingPhone(string $phone, ?int $exceptOwnerId = null): Collection
    {
        $normalised = Owner::normalisePhone($phone);

        if ($normalised === '') {
            return new Collection;
        }

        return Owner::query()
            ->where(fn ($q) => $q
                ->where('phone_normalized', $normalised)
                ->orWhereIn('id', PortalAccountOwner::query()
                    ->select('owner_id')
                    ->where('needs_review', false)
                    ->whereIn('portal_account_id', PortalAccount::query()->select('id')->where('phone_normalized', $normalised))))
            ->when($exceptOwnerId !== null, fn ($q) => $q->whereKeyNot($exceptOwnerId))
            ->orderBy('name')
            ->get();
    }

    /** Records the action. Public so sign-in and switching can use the same log. */
    public function audit(string $action, ?PortalAccount $account, ?Owner $owner, ?User $by): void
    {
        AuditLog::create([
            'user_id' => $by?->id,
            'owner_id' => $owner?->id,
            'portal_account_id' => $account?->id,
            'action' => $action,
            'target_type' => self::AUDIT_TARGET,
            'target_id' => $account?->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, PortalAccountOwner> */
    private function selfGrants(Owner $owner): \Illuminate\Support\Collection
    {
        return PortalAccountOwner::query()
            ->with('account')
            ->where('owner_id', $owner->id)
            ->where('relation', PortalRelation::Self_->value)
            ->where('needs_review', false)
            ->get();
    }

    /** The account's phone is now the owner's; any other "self" account steps down. */
    private function makeOwnPhone(PortalAccount $account, Owner $owner, ?User $by): void
    {
        foreach ($this->selfGrants($owner) as $other) {
            if ($other->portal_account_id !== $account->id) {
                $this->revoke($other, $by);
            }
        }

        if (Owner::normalisePhone((string) $owner->phone) !== $account->phone_normalized) {
            $owner->forceFill(['phone' => $account->phone])->save();
        }
    }

    private function endSessions(PortalAccount $account): void
    {
        $account->increment('session_version');
        DB::table('personal_access_tokens')->where('portal_account_id', $account->id)->delete();
    }
}
