<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Http\Middleware\EnsurePortalAccount;
use App\Livewire\Owners\OwnerFormModal;
use App\Livewire\PortalAccounts\AccountIndex;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Import\ParcelGeoJsonImporter;
use App\Services\Owner\OwnerHoldings;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Gaps the whole-branch review found, each pinned by a test.
 */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_updates_re_check_the_portal_account(): void
    {
        // /livewire/update re-applies only the persistent middleware of the page's route.
        $this->assertContains(EnsurePortalAccount::class, app(PersistentMiddleware::class)->getPersistentMiddleware());
    }

    public function test_the_owner_modal_asks_before_a_phone_opens_another_owner(): void
    {
        $staff = $this->staff(['owners.edit', 'owners.create']);
        $stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000009']);
        $access = app(PortalAccessService::class);
        $access->grant($access->accountForPhone('0500000009'), $stranger, PortalRelation::Self_, true, PortalGrantSource::Staff, $staff);
        $owner = Owner::create(['name' => 'جديد', 'national_id' => '1000000001']);
        $this->actingAs($staff);

        $modal = Livewire::test(OwnerFormModal::class)
            ->call('openEdit', $owner->id)
            ->set('form.phone', '0500000009')
            ->call('save')
            ->assertSet('sharedWith', ['غريب']);

        $this->assertNull($owner->fresh()->phone, 'nothing is saved until confirmed');
        $this->assertSame([$stranger->id], PortalAccount::findByPhone('0500000009')?->usableOwnerIds());

        $modal->call('confirmSharedPhone');
        $this->assertEqualsCanonicalizing([$stranger->id, $owner->id], PortalAccount::findByPhone('0500000009')?->usableOwnerIds());
    }

    public function test_an_import_with_shared_phones_names_every_phone_it_shared(): void
    {
        $base = json_decode((string) file_get_contents(base_path('tests/fixtures/import/parcels.geojson')), true)['features'][0];
        $rows = [];
        foreach ([['1066-2516', '917815000449', '1041721927'], ['1308-1637', '393523002126', '1041721885']] as [$geo, $deed, $nid]) {
            $row = $base;
            $row['properties'] = array_merge($base['properties'], ['Geo_ID' => $geo, 'Deed_No' => $deed, 'Woner_ID' => $nid, 'Name' => 'مالك '.$nid, 'Phone' => '536664026', 'Parent_Owner_ID' => null]);
            $rows[] = $row;
        }

        $result = app(ParcelGeoJsonImporter::class)->importFeatures($rows, ['shared_phones' => true]);

        $this->assertStringContainsString('536664026', implode(' ', $result->warnings), 'a phone now opening several owners is reported');
    }

    public function test_recorded_and_blank_shares_never_add_up_past_the_whole(): void
    {
        $plan = Plan::create(['plan_no' => '1']);
        $parcel = Parcel::create(['parcel_no' => '1', 'geo_id' => '1-1', 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => '111', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        [$a, $b, $c] = [Owner::create(['name' => 'أ', 'national_id' => '1000000001']), Owner::create(['name' => 'ب', 'national_id' => '1000000002']), Owner::create(['name' => 'ج', 'national_id' => '1000000003'])];
        $deed->owners()->attach([$a->id => ['ownership_share' => 50], $b->id => ['ownership_share' => null], $c->id => ['ownership_share' => null]]);

        $shares = app(OwnerHoldings::class)->shares($parcel->fresh());

        $this->assertSame(0.5, $shares[$a->id]);
        $this->assertEqualsWithDelta(0.25, $shares[$b->id], 0.0001, 'the blanks split what the recorded shares leave');
        $this->assertEqualsWithDelta(1.0, array_sum($shares), 0.0001);
    }

    public function test_recorded_shares_over_the_whole_are_scaled_down(): void
    {
        $plan = Plan::create(['plan_no' => '1']);
        $parcel = Parcel::create(['parcel_no' => '1', 'geo_id' => '1-1', 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => '111', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        [$a, $b] = [Owner::create(['name' => 'أ', 'national_id' => '1000000001']), Owner::create(['name' => 'ب', 'national_id' => '1000000002'])];
        $deed->owners()->attach([$a->id => ['ownership_share' => 100], $b->id => ['ownership_share' => 100]]);

        $this->assertEqualsWithDelta(1.0, array_sum(app(OwnerHoldings::class)->shares($parcel->fresh())), 0.0001);
    }

    public function test_a_new_account_as_the_owner_takes_one_owner_only(): void
    {
        $this->actingAs($this->staff(['portal_accounts.view', 'portal_accounts.manage']));
        [$a, $b] = [Owner::create(['name' => 'أ', 'national_id' => '1000000001', 'phone' => '0500000001']), Owner::create(['name' => 'ب', 'national_id' => '1000000002', 'phone' => '0500000002'])];

        Livewire::test(AccountIndex::class)
            ->set('newPhone', '0536664026')
            ->set('newOwnerIds', [$a->id, $b->id])
            ->set('newRelation', 'self')
            ->call('create')
            ->assertHasErrors('newOwnerIds');

        $this->assertSame('0500000001', $a->fresh()->phone);
    }

    public function test_renaming_reads_no_account_outside_the_users_scope(): void
    {
        $staff = $this->staff(['portal_accounts.view', 'portal_accounts.manage']);
        $mine = Owner::create(['name' => 'ضمن النطاق', 'national_id' => '1000000001']);
        $other = Owner::create(['name' => 'خارج النطاق', 'national_id' => '1000000002']);
        $staff->scopedOwners()->attach($mine->id);
        $access = app(PortalAccessService::class);
        $hidden = $access->accountForPhone('0500000002', 'حساب سري');
        $access->grant($hidden, $other, PortalRelation::Delegate, true, PortalGrantSource::Staff, null);
        $this->actingAs($staff);

        Livewire::test(AccountIndex::class)->call('startRename', $hidden->id)->assertForbidden();
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}
