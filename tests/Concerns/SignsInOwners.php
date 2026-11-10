<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Services\Portal\PortalAccessService;

/**
 * Signs an owner in to the portal the way a real sign-in does: through a
 * portal account that opens them. A session or token with no account behind
 * it is refused by the portal.account middleware.
 */
trait SignsInOwners
{
    /** Signs the owner in to the web portal, through the given account or their own. */
    protected function actingAsOwner(Owner $owner, ?PortalAccount $account = null): static
    {
        $account ??= $this->portalAccountFor($owner);

        $this->actingAs($owner, 'owner');

        return $this->withSession([
            'portal_account_id' => $account->id,
            'portal_session_version' => $account->fresh()?->session_version ?? $account->session_version,
        ]);
    }

    /** Signs the owner in to the API with a real token, through the given account or their own. */
    protected function apiAsOwner(Owner $owner, ?PortalAccount $account = null): static
    {
        $account ??= $this->portalAccountFor($owner);

        $token = $owner->createToken('mobile');
        $token->accessToken->forceFill(['portal_account_id' => $account->id])->save();

        // The guard keeps the user it resolved for the last request; a new
        // token must be read afresh.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token->plainTextToken);
    }

    /**
     * The owner's own account. An owner with no phone gets one made for the
     * test, its grant written directly so their record is left exactly as the
     * test built it (the service would write the account's phone onto them).
     */
    protected function portalAccountFor(Owner $owner): PortalAccount
    {
        $access = app(PortalAccessService::class);

        if ($owner->phone !== null && Owner::normalisePhone($owner->phone) !== '') {
            $account = $access->accountForPhone($owner->phone);
            $access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, null);

            return $account;
        }

        $account = $access->accountForPhone('059'.str_pad((string) $owner->id, 7, '0', STR_PAD_LEFT));
        PortalAccountOwner::query()->firstOrCreate(
            ['portal_account_id' => $account->id, 'owner_id' => $owner->id],
            ['relation' => PortalRelation::Self_, 'can_manage' => true, 'source' => PortalGrantSource::Staff],
        );

        return $account;
    }
}
