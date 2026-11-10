<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

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
 * The mobile app signs in through a portal account too: the token names the
 * account, and every request checks the account still opens the owner.
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    private PortalAccessService $access;

    private User $staff;

    private Owner $najla;

    private Owner $saud;

    private PortalAccount $office;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.mobile_otp.test_code' => '6666']);

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
        $this->najla = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927']);
        $this->saud = Owner::create(['name' => 'سعود', 'national_id' => '1041721885']);

        $this->office = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي');
        $this->access->grant($this->office, $this->najla, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($this->office, $this->saud, PortalRelation::Delegate, false, PortalGrantSource::Staff, $this->staff);
    }

    public function test_verify_returns_the_owner_list(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0536664026'])->assertOk();

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0536664026', 'otp' => '6666'])
            ->assertOk()
            ->assertJsonPath('data.owner.id', $this->najla->id)
            ->assertJsonPath('data.account.id', $this->office->id)
            ->assertJsonPath('data.account.name', 'مكتب ركن الشمال الذهبي')
            ->assertJsonCount(2, 'data.owners')
            ->assertJsonPath('data.owners.1.id', $this->saud->id)
            ->assertJsonPath('data.owners.1.relation', 'delegate')
            ->assertJsonPath('data.owners.1.can_manage', false);
    }

    public function test_a_new_sign_in_revokes_only_this_accounts_tokens(): void
    {
        $najlasOwn = $this->access->accountForPhone('0500000001');
        $this->access->grant($najlasOwn, $this->najla, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $najlasToken = $this->apiToken($this->najla, $najlasOwn);
        $officeToken = $this->apiToken($this->najla, $this->office);

        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0536664026']);
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0536664026', 'otp' => '6666'])->assertOk();
        Auth::forgetGuards();

        $this->withToken($officeToken)->getJson('/api/v1/me')->assertStatus(401);
        Auth::forgetGuards();
        $this->withToken($najlasToken)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_revoked_grant_makes_the_token_401(): void
    {
        $token = $this->apiToken($this->najla, $this->office);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->access->revoke($this->office->grants()->where('owner_id', $this->najla->id)->firstOrFail(), $this->staff);
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_a_deactivated_account_makes_the_token_401(): void
    {
        $token = $this->apiToken($this->najla, $this->office);

        $this->access->deactivate($this->office, $this->staff);
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_a_token_without_an_account_is_401(): void
    {
        $token = $this->najla->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_switching_issues_a_token_for_the_other_owner(): void
    {
        $token = $this->apiToken($this->najla, $this->office);

        $switched = $this->withToken($token)->postJson('/api/v1/auth/switch-owner', ['owner_id' => $this->saud->id])
            ->assertOk()
            ->assertJsonPath('data.owner.id', $this->saud->id)
            ->json('data.token');
        Auth::forgetGuards();

        $this->withToken($switched)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $this->saud->id);
        $this->assertSame($this->saud->id, $this->office->fresh()->last_owner_id);
    }

    public function test_the_old_token_stops_working_after_a_switch(): void
    {
        $token = $this->apiToken($this->najla, $this->office);

        $this->withToken($token)->postJson('/api/v1/auth/switch-owner', ['owner_id' => $this->saud->id])->assertOk();
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_switching_to_an_ungranted_owner_is_403(): void
    {
        $stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000009']);
        $token = $this->apiToken($this->najla, $this->office);

        $this->withToken($token)->postJson('/api/v1/auth/switch-owner', ['owner_id' => $stranger->id])->assertForbidden();
    }

    public function test_overview_lists_the_accounts_owners(): void
    {
        $token = $this->apiToken($this->najla, $this->office);

        $this->withToken($token)->getJson('/api/v1/account/overview')
            ->assertOk()
            ->assertJsonCount(2, 'data.owners')
            ->assertJsonPath('data.owners.0.owner_id', $this->najla->id)
            ->assertJsonPath('data.totals.parcels', 0);
    }

    public function test_view_only_token_cannot_post_a_change_request(): void
    {
        $token = $this->apiToken($this->saud, $this->office);

        $this->withToken($token)->postJson('/api/v1/modification-requests', ['parcel_id' => 1, 'field_name' => 'asset_type', 'new_value' => 'أرض'])
            ->assertForbidden();
    }

    private function apiToken(Owner $owner, PortalAccount $account): string
    {
        $issued = $owner->createToken('mobile');
        $issued->accessToken->forceFill(['portal_account_id' => $account->id])->save();

        return $issued->plainTextToken;
    }
}
