<?php

declare(strict_types=1);

namespace App\Support\Portal;

use App\Enums\PortalPermission;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Services\Portal\PortalAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who is signed in to the portal, for this request.
 *
 * The `owner` guard (web) and the Sanctum token (API) keep authenticating an
 * Owner: the owner being acted for. Everything that scopes data reads that, as
 * it always has. Beside it stands the portal account — the phone that signed
 * in — which says which owners may be switched to and whether this one may
 * be acted for or only looked at.
 *
 * Web: the account id and its session version live in the session.
 * API: the token carries the account id.
 */
class PortalContext
{
    public const SESSION_ACCOUNT = 'portal_account_id';

    public const SESSION_VERSION = 'portal_session_version';

    private ?PortalAccount $account = null;

    /** Which session or token $account was read for, so a later request never reuses it. */
    private ?string $resolvedFor = null;

    /** The owner being acted for. */
    public function owner(): ?Owner
    {
        $user = Auth::user();

        return $user instanceof Owner ? $user : null;
    }

    /** The account that signed in. */
    public function account(): ?PortalAccount
    {
        $token = $this->token();
        $id = $token !== null
            ? $token->getAttribute('portal_account_id')
            : (Auth::guard('owner')->check() ? session(self::SESSION_ACCOUNT) : null);
        // Tied to this request object: an account changed since (deactivated,
        // signed out) is read afresh, even where the container outlives it.
        $key = spl_object_id(request()).':'.($token !== null ? 'token:'.$token->getKey() : 'session').':'.($id ?? '-');

        if ($this->resolvedFor !== $key) {
            $this->resolvedFor = $key;
            $this->account = $id === null ? null : PortalAccount::find($id);
        }

        return $this->account;
    }

    /** Whether this request came in with an app token rather than a web session. */
    public function viaToken(): bool
    {
        return $this->token() !== null;
    }

    /** The web session still belongs to the account as it now stands. */
    public function sessionIsCurrent(): bool
    {
        $account = $this->account();

        return $account !== null && (int) session(self::SESSION_VERSION) === $account->session_version;
    }

    /** @return list<int> the owners this account may switch to */
    public function ownerIds(): array
    {
        return $this->account()?->usableOwnerIds() ?? [];
    }

    /** More than one owner to choose from. */
    public function hasMany(): bool
    {
        return count($this->ownerIds()) > 1;
    }

    /** The grant on the owner being acted for. */
    public function grant(): ?PortalAccountOwner
    {
        $account = $this->account();
        $owner = $this->owner();

        return $account === null || $owner === null ? null : $account->usableGrants()->where('owner_id', $owner->id)->first();
    }

    /** May act for this owner, not only look. */
    public function canManage(): bool
    {
        return (bool) $this->grant()?->can_manage;
    }

    /** May do this one thing for the owner being acted for (see PortalAccountOwner::allows()). */
    public function can(PortalPermission $permission): bool
    {
        return (bool) $this->grant()?->allows($permission);
    }

    /** Signed in as the owner themselves. */
    public function isSelf(): bool
    {
        return (bool) $this->grant()?->isSelf();
    }

    /** @return list<array{id: int, name: string, relation: string, can_manage: bool, current: bool}> */
    public function switchable(): array
    {
        $account = $this->account();
        $current = $this->owner()?->id;

        if ($account === null) {
            return [];
        }

        return $account->usableGrants()->with('owner:id,name')->orderBy('id')->get()
            ->map(fn (PortalAccountOwner $grant): array => [
                'id' => (int) $grant->owner_id,
                'name' => (string) $grant->owner?->name,
                'relation' => $grant->relation->value,
                'can_manage' => $grant->can_manage,
                'current' => (int) $grant->owner_id === $current,
            ])
            ->all();
    }

    /** Web sign-in: act for the owner, through the account. */
    public function signIn(PortalAccount $account, Owner $owner): void
    {
        Auth::guard('owner')->login($owner);
        session()->regenerate();
        session([self::SESSION_ACCOUNT => $account->id, self::SESSION_VERSION => $account->session_version]);
        $account->forceFill(['last_owner_id' => $owner->id, 'last_login_at' => now()])->save();

        $this->remember($account);
        app(PortalAccessService::class)->audit('portal_signed_in', $account, $owner, null);
    }

    /**
     * The owner being acted for, standing for every owner the account opens:
     * what the home, the parcel list, the map and the documents read, so an
     * account for a family or a company sees all their land on one page.
     * Given a permission, only the owners whose grant allows it count — the
     * documents of an owner who withheld them stay out of the shared list.
     */
    public function readingOwner(?PortalPermission $allowing = null): ?Owner
    {
        $owner = $this->owner();
        $account = $this->account();

        if ($owner !== null && $account !== null && $this->hasMany()) {
            $grants = $account->usableGrants()->with('account')->get();
            $ids = $grants
                ->filter(fn (PortalAccountOwner $grant): bool => $allowing === null || $grant->allows($allowing))
                ->pluck('owner_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
            // Standing for nobody must read nothing, never fall back to the owner alone.
            $owner->standingFor($ids === [] ? [0, -1] : $ids);
        }

        return $owner;
    }

    /** Whether any owner the account opens lets it do this — what decides showing a shared page at all. */
    public function canAny(PortalPermission $permission): bool
    {
        $account = $this->account();

        if ($account === null || ! $this->hasMany()) {
            return $this->can($permission);
        }

        return $account->usableGrants()->with('account')->get()->contains(fn (PortalAccountOwner $grant): bool => $grant->allows($permission));
    }

    /**
     * Web: a parcel listed on a read page may belong to another of the
     * account's owners. Act for the one who holds it, so the page, and what
     * it allows, are that owner's. False when the account opens nobody who does.
     */
    public function actAsHolderOf(int $parcelId): bool
    {
        $account = $this->account();

        if ($account === null || ! $this->hasMany()) {
            return false;
        }

        $holder = Owner::query()
            ->whereKey($account->usableOwnerIds())
            ->whereKeyNot((int) $this->owner()?->id)
            ->get()
            ->first(fn (Owner $owner): bool => $owner->parcels()->whereKey($parcelId)->exists());

        if ($holder === null) {
            return false;
        }

        $this->switchTo($holder);

        return true;
    }

    /**
     * Web: act for another owner the account opens.
     *
     * @throws AuthorizationException when the account does not open them
     */
    public function switchTo(Owner $owner): void
    {
        $account = $this->account();

        if ($account === null || ! $account->opens((int) $owner->id)) {
            throw new AuthorizationException;
        }

        Auth::guard('owner')->login($owner);
        session()->regenerate();
        session([self::SESSION_ACCOUNT => $account->id, self::SESSION_VERSION => $account->session_version]);
        $account->forceFill(['last_owner_id' => $owner->id])->save();

        app(PortalAccessService::class)->audit('portal_owner_switched', $account, $owner, null);
    }

    /** Web sign-out: the owner guard only, so a staff session in the same browser stays. */
    public function signOut(): void
    {
        Auth::guard('owner')->logout();
        session()->forget([self::SESSION_ACCOUNT, self::SESSION_VERSION, 'portal_otp_account_id']);
        $this->remember(null);
    }

    private function remember(?PortalAccount $account): void
    {
        $this->account = $account;
        $this->resolvedFor = null;
    }

    private function token(): ?PersonalAccessToken
    {
        $token = $this->owner()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }
}
