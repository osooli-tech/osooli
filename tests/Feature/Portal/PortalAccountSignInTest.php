<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Signing in to the web portal goes through a portal account, and the account
 * is checked again on every request.
 */
class PortalAccountSignInTest extends TestCase
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

    public function test_a_single_owner_phone_signs_straight_into_its_owner(): void
    {
        $owner = $this->owner('1', '0500000001');
        $account = $this->ownAccount($owner);

        $this->post(route('portal.login.submit'), ['phone' => '0500000001'])->assertRedirect(route('portal.otp'));
        $this->post(route('portal.otp.verify'), ['otp' => '6666'])->assertRedirect(route('portal.dashboard'));

        $this->assertSame($owner->id, Auth::guard('owner')->id());
        $this->assertSame($account->id, session('portal_account_id'));
        $this->assertNotNull($account->fresh()->last_login_at);
        $this->get(route('portal.dashboard'))->assertOk();
    }

    public function test_a_phone_whose_only_grant_is_pending_is_not_registered(): void
    {
        $owner = $this->owner('1', null);
        $account = $this->access->accountForPhone('0500000009');
        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Import, null, needsReview: true);

        $this->post(route('portal.login.submit'), ['phone' => '0500000009'])->assertSessionHasErrors('phone');
    }

    public function test_a_deactivated_account_is_signed_out_on_the_next_request(): void
    {
        $owner = $this->owner('1', '0500000001');
        $account = $this->ownAccount($owner);
        $this->actingAsOwner($owner, $account)->get(route('portal.dashboard'))->assertOk();

        $this->access->deactivate($account, $this->staff);

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_a_revoked_grant_moves_the_session_to_another_owner(): void
    {
        [$a, $b] = [$this->owner('1', null), $this->owner('2', null)];
        $office = $this->access->accountForPhone('0536664026');
        $grantA = $this->access->grant($office, $a, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($office, $b, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->actingAsOwner($a, $office)->get(route('portal.dashboard'))->assertOk();

        $this->access->revoke($grantA, $this->staff);

        $this->get(route('portal.parcels.index'))->assertRedirect(route('portal.dashboard'));
        $this->assertSame($b->id, Auth::guard('owner')->id());
    }

    public function test_a_revoked_last_grant_signs_out(): void
    {
        $owner = $this->owner('1', null);
        $office = $this->access->accountForPhone('0536664026');
        $grant = $this->access->grant($office, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->actingAsOwner($owner, $office)->get(route('portal.dashboard'))->assertOk();

        $this->access->revoke($grant, $this->staff);

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_an_archived_owner_is_left_on_the_next_request(): void
    {
        $owner = $this->owner('1', '0500000001');
        $this->actingAsOwner($owner)->get(route('portal.dashboard'))->assertOk();

        // archive() stamps the signed-in user; here that is the owner, not staff.
        $owner->delete();

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    }

    public function test_sign_out_everywhere_ends_the_web_session(): void
    {
        $owner = $this->owner('1', '0500000001');
        $account = $this->ownAccount($owner);
        $this->actingAsOwner($owner, $account)->get(route('portal.dashboard'))->assertOk();

        $this->access->signOutEverywhere($account, $this->staff);

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    }

    public function test_a_session_without_an_account_is_signed_out(): void
    {
        $owner = $this->owner('1', '0500000001');

        $this->actingAs($owner, 'owner')->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_logging_out_forgets_the_account(): void
    {
        $owner = $this->owner('1', '0500000001');
        $this->actingAsOwner($owner)->post(route('portal.logout'))->assertRedirect(route('portal.login'));

        $this->assertFalse(session()->has('portal_account_id'));
    }

    private function owner(string $n, ?string $phone): Owner
    {
        return Owner::create(['name' => 'مالك '.$n, 'national_id' => '100000000'.$n, 'phone' => $phone]);
    }

    private function ownAccount(Owner $owner): PortalAccount
    {
        $account = $this->access->accountForPhone((string) $owner->phone);
        $this->access->grant($account, $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);

        return $account;
    }
}
