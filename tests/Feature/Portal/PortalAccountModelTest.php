<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A portal account is one phone; its grants say which owners it opens.
 */
class PortalAccountModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_is_normalised_and_unique(): void
    {
        $account = PortalAccount::create(['phone' => '0536664026']);
        $this->assertSame('536664026', $account->phone_normalized);

        $this->expectException(UniqueConstraintViolationException::class);
        PortalAccount::create(['phone' => '966536664026']);
    }

    public function test_usable_grants_skip_pending_and_archived_owners(): void
    {
        [$a, $b, $c] = [$this->owner('1'), $this->owner('2'), $this->owner('3')];
        $account = PortalAccount::create(['phone' => '0536664026']);
        $this->grant($account, $a);
        $this->grant($account, $b, needsReview: true);
        $this->grant($account, $c);
        $c->archive();

        $this->assertSame([$a->id], $account->usableOwnerIds());
        $this->assertSame([$a->id], $account->owners()->pluck('owners.id')->all());
    }

    public function test_default_owner_prefers_last_then_self_then_oldest(): void
    {
        [$a, $b, $c] = [$this->owner('1'), $this->owner('2'), $this->owner('3')];
        $account = PortalAccount::create(['phone' => '0536664026']);
        $this->grant($account, $a, PortalRelation::Delegate);
        $this->grant($account, $b, PortalRelation::Self_);
        $this->grant($account, $c, PortalRelation::Delegate);

        $this->assertSame($b->id, $account->defaultOwner()?->id, 'the account holder first');

        $account->update(['last_owner_id' => $c->id]);
        $this->assertSame($c->id, $account->fresh()->defaultOwner()?->id, 'then the owner last worked on');

        PortalAccountOwner::where('owner_id', $b->id)->delete();
        $account->update(['last_owner_id' => null]);
        $this->assertSame($a->id, $account->fresh()->defaultOwner()?->id, 'else the oldest grant');
    }

    public function test_find_by_phone_ignores_inactive_accounts(): void
    {
        $account = PortalAccount::create(['phone' => '0536664026']);
        $this->assertSame($account->id, PortalAccount::findByPhone('+966 53 666 4026')?->id);

        $account->update(['is_active' => false]);
        $this->assertNull(PortalAccount::findByPhone('0536664026'));
    }

    private function owner(string $n): Owner
    {
        return Owner::create(['name' => 'مالك '.$n, 'national_id' => '100000000'.$n]);
    }

    private function grant(PortalAccount $account, Owner $owner, PortalRelation $relation = PortalRelation::Self_, bool $needsReview = false): PortalAccountOwner
    {
        return PortalAccountOwner::create([
            'portal_account_id' => $account->id,
            'owner_id' => $owner->id,
            'relation' => $relation,
            'can_manage' => true,
            'source' => PortalGrantSource::Staff,
            'needs_review' => $needsReview,
        ]);
    }
}
