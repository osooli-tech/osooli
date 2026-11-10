<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every change to who may open an owner goes through one service.
 */
class PortalAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private PortalAccessService $access;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
    }

    public function test_account_for_phone_reuses_the_account_in_any_format(): void
    {
        $first = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال');
        $again = $this->access->accountForPhone('+966536664026');

        $this->assertSame($first->id, $again->id);
        $this->assertSame('مكتب ركن الشمال', $again->name);
    }

    public function test_grant_is_idempotent(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0536664026');

        $this->access->grant($account, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($account, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);

        $this->assertSame(1, PortalAccountOwner::count());
    }

    public function test_a_pending_regrant_never_downgrades_a_usable_grant(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0536664026');

        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Import, null, needsReview: true);

        $this->assertSame([$owner->id], $account->usableOwnerIds());
    }

    public function test_a_self_grant_writes_the_owners_phone(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0536664026');

        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);

        $this->assertSame('0536664026', $owner->fresh()->phone);
    }

    public function test_change_owner_phone_moves_the_self_grant_and_keeps_the_invariant(): void
    {
        $owner = $this->owner('1');
        $old = $this->access->accountForPhone('0500000001');
        $this->access->grant($old, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);

        $this->access->changeOwnerPhone($owner, '0500000002', $this->staff);

        $this->assertSame([], $old->usableOwnerIds());
        $new = PortalAccount::findByPhone('0500000002');
        $this->assertSame([$owner->id], $new?->usableOwnerIds());
        $this->assertTrue($new->grants()->first()->isSelf());
        $this->assertSame('0500000002', $owner->fresh()->phone);
    }

    public function test_clearing_the_phone_revokes_the_self_grant(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0500000001');
        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);

        $this->access->changeOwnerPhone($owner, null, $this->staff);

        $this->assertSame([], $account->usableOwnerIds());
        $this->assertNull($owner->fresh()->phone);
    }

    public function test_change_owner_phone_keeps_delegate_grants(): void
    {
        $owner = $this->owner('1');
        $office = $this->access->accountForPhone('0536664026');
        $this->access->grant($office, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);

        $this->access->changeOwnerPhone($owner, '0500000002', $this->staff);

        $this->assertSame([$owner->id], $office->usableOwnerIds());
    }

    public function test_approving_a_pending_self_grant_moves_the_phone(): void
    {
        $owner = $this->owner('1');
        $old = $this->access->accountForPhone('0500000001');
        $this->access->grant($old, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $new = $this->access->accountForPhone('0500000002');
        $pending = $this->access->grant($new, $owner, PortalRelation::Self_, true, PortalGrantSource::Import, null, needsReview: true);

        $this->assertSame([], $new->usableOwnerIds(), 'pending opens nothing');
        $this->assertSame('0500000001', $owner->fresh()->phone, 'pending does not touch the phone');

        $this->access->approve($pending, $this->staff);

        $this->assertSame([$owner->id], $new->usableOwnerIds());
        $this->assertSame([], $old->usableOwnerIds());
        $this->assertSame('0500000002', $owner->fresh()->phone);
    }

    public function test_sign_out_everywhere_bumps_the_version_and_drops_tokens(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0500000001');
        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $token = $owner->createToken('mobile');
        $token->accessToken->forceFill(['portal_account_id' => $account->id])->save();

        $this->access->signOutEverywhere($account, $this->staff);

        $this->assertSame(2, $account->fresh()->session_version);
        $this->assertSame(0, $owner->tokens()->count());
    }

    public function test_owners_sharing_phone_names_every_owner_it_reaches(): void
    {
        [$a, $b, $c] = [$this->owner('1'), $this->owner('2'), $this->owner('3')];
        $office = $this->access->accountForPhone('0536664026');
        $this->access->grant($office, $a, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($office, $b, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);

        $ids = $this->access->ownersSharingPhone('536664026', exceptOwnerId: $c->id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
    }

    public function test_every_write_is_audited(): void
    {
        $owner = $this->owner('1');
        $account = $this->access->accountForPhone('0500000001');
        $grant = $this->access->grant($account, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->setManage($grant, false, $this->staff);
        $this->access->rename($account, 'وكيل', $this->staff);
        $this->access->deactivate($account, $this->staff);
        $this->access->activate($account, $this->staff);
        $this->access->revoke($grant->fresh(), $this->staff);

        $actions = AuditLog::where('target_type', PortalAccessService::AUDIT_TARGET)->pluck('action')->all();

        $this->assertSame(['portal_grant_added', 'portal_grant_manage_changed', 'portal_account_renamed', 'portal_account_deactivated', 'portal_account_activated', 'portal_grant_revoked'], $actions);
        $this->assertSame($this->staff->id, AuditLog::where('action', 'portal_grant_added')->value('user_id'));
    }

    private function owner(string $n): Owner
    {
        return Owner::create(['name' => 'مالك '.$n, 'national_id' => '100000000'.$n]);
    }
}
