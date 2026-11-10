<?php

declare(strict_types=1);

namespace Tests\Feature\PortalAccounts;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Livewire\Owners\OwnerEditForm;
use App\Livewire\Owners\PortalAccessPanel;
use App\Livewire\PortalAccounts\AccountIndex;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Staff decide which phones open which owners: on the owner, on the accounts
 * page, and when a phone is changed.
 */
class PortalAccountsStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    /** @var list<Owner> */
    private array $family = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['is_active' => true]);
        foreach (['owners.edit', 'portal_accounts.view', 'portal_accounts.manage', 'parcels.view'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $this->staff->givePermissionTo(['owners.edit', 'portal_accounts.view', 'portal_accounts.manage', 'parcels.view']);

        foreach (['نجلاء', 'سعود', 'عبدالله', 'احمد', 'بندر'] as $i => $name) {
            $this->family[] = Owner::create(['name' => $name, 'national_id' => '104172190'.$i]);
        }
    }

    public function test_creating_an_office_account_over_five_owners_in_one_step(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(AccountIndex::class)
            ->set('newPhone', '0536664026')
            ->set('newName', 'مكتب ركن الشمال الذهبي للعقارات')
            ->set('newOwnerIds', array_map(fn (Owner $o): int => $o->id, $this->family))
            ->set('newRelation', 'delegate')
            ->set('newCanManage', true)
            ->call('create')
            ->assertHasNoErrors();

        $account = PortalAccount::findByPhone('0536664026');
        $this->assertSame('مكتب ركن الشمال الذهبي للعقارات', $account?->name);
        $this->assertCount(5, $account->usableOwnerIds());
        $this->assertSame(0, $account->grants()->where('relation', 'self')->count());
        $this->assertNull($this->family[0]->fresh()->phone, 'a delegate account does not take the owners\' phones');
    }

    public function test_the_page_lists_accounts_with_their_owners(): void
    {
        $account = $this->office();

        $this->actingAs($this->staff)->get(route('portal-accounts.index'))
            ->assertOk()
            ->assertSee('مكتب ركن الشمال')
            ->assertSee('536664026');
        $this->assertCount(5, $account->usableOwnerIds());
    }

    public function test_the_review_tab_lists_pending_grants_and_approving_one_activates_it(): void
    {
        $account = app(PortalAccessService::class)->accountForPhone('0536664026');
        $pending = app(PortalAccessService::class)->grant($account, $this->family[1], PortalRelation::Self_, true, PortalGrantSource::Import, null, needsReview: true);
        $this->actingAs($this->staff);

        Livewire::test(AccountIndex::class)
            ->set('tab', 'review')
            ->assertSee('سعود')
            ->call('approve', $pending->id);

        $this->assertSame([$this->family[1]->id], $account->usableOwnerIds());
    }

    public function test_rejecting_a_pending_grant_removes_it(): void
    {
        $account = app(PortalAccessService::class)->accountForPhone('0536664026');
        $pending = app(PortalAccessService::class)->grant($account, $this->family[1], PortalRelation::Self_, true, PortalGrantSource::Import, null, needsReview: true);
        $this->actingAs($this->staff);

        Livewire::test(AccountIndex::class)->call('reject', $pending->id);

        $this->assertSame(0, $account->grants()->count());
    }

    public function test_deactivating_and_signing_out_everywhere(): void
    {
        $account = $this->office();
        $this->actingAs($this->staff);

        Livewire::test(AccountIndex::class)->call('signOutEverywhere', $account->id)->call('deactivate', $account->id);

        $account->refresh();
        $this->assertFalse($account->is_active);
        $this->assertSame(3, $account->session_version);
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $clerk = User::factory()->create(['is_active' => true]);

        $this->actingAs($clerk)->get(route('portal-accounts.index'))->assertForbidden();
    }

    public function test_staff_see_which_phones_open_the_owner_and_can_add_one(): void
    {
        $this->office();
        $this->actingAs($this->staff);

        Livewire::test(PortalAccessPanel::class, ['ownerId' => $this->family[0]->id])
            ->assertSee('536664026')
            ->set('phone', '0500000001')
            ->set('relation', 'self')
            ->call('addPhone')
            ->assertHasNoErrors();

        $this->assertSame('0500000001', $this->family[0]->fresh()->phone);
        $this->assertSame([$this->family[0]->id], PortalAccount::findByPhone('0500000001')?->usableOwnerIds());
    }

    public function test_revoking_and_view_only_from_the_owner_panel(): void
    {
        $account = $this->office();
        $grant = $account->grants()->where('owner_id', $this->family[0]->id)->firstOrFail();
        $this->actingAs($this->staff);

        Livewire::test(PortalAccessPanel::class, ['ownerId' => $this->family[0]->id])->call('toggleManage', $grant->id);
        $this->assertFalse($grant->fresh()->can_manage);

        Livewire::test(PortalAccessPanel::class, ['ownerId' => $this->family[0]->id])->call('revoke', $grant->id);
        $this->assertNotContains($this->family[0]->id, $account->usableOwnerIds());
    }

    public function test_saving_a_new_owner_phone_moves_the_self_grant(): void
    {
        $owner = $this->family[0];
        $access = app(PortalAccessService::class);
        $access->grant($access->accountForPhone('0500000001'), $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $this->actingAs($this->staff);

        Livewire::test(OwnerEditForm::class, ['ownerId' => $owner->id])
            ->call('edit')
            ->set('phone', '0500000002')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([], PortalAccount::findByPhone('0500000001')?->usableOwnerIds());
        $this->assertSame([$owner->id], PortalAccount::findByPhone('0500000002')?->usableOwnerIds());
    }

    public function test_saving_a_phone_that_opens_another_owner_asks_first(): void
    {
        $this->office();
        $owner = Owner::create(['name' => 'جديد', 'national_id' => '1000000077']);
        $this->actingAs($this->staff);

        $form = Livewire::test(OwnerEditForm::class, ['ownerId' => $owner->id])
            ->call('edit')
            ->set('phone', '0536664026')
            ->call('save')
            ->assertSet('sharedWith', fn (array $names): bool => in_array('نجلاء', $names, true));

        $this->assertNull($owner->fresh()->phone, 'nothing is saved until confirmed');

        $form->call('confirmSharedPhone');
        $this->assertSame('0536664026', $owner->fresh()->phone);
    }

    private function office(): PortalAccount
    {
        $access = app(PortalAccessService::class);
        $account = $access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي للعقارات');
        foreach ($this->family as $owner) {
            $access->grant($account, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        }

        return $account;
    }
}
