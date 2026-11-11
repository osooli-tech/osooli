<?php

declare(strict_types=1);

namespace Tests\Feature\PortalAccounts;

use App\Enums\DeedStatus;
use App\Enums\PortalGrantSource;
use App\Enums\PortalPermission;
use App\Enums\PortalRelation;
use App\Livewire\PortalAccounts\AccountIndex;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A company acting as agent for owners: it owns nothing, lands on all their
 * land at once, and does for each owner only what staff allow — while each
 * owner signing in alone sees just their own, as before.
 */
class AgentAccountTest extends TestCase
{
    use RefreshDatabase;

    private PortalAccessService $access;

    private User $staff;

    private Owner $najla;

    private Owner $saud;

    private PortalAccount $office;

    private Parcel $najlasParcel;

    private Parcel $saudsParcel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ParcelPhoto::PRIVATE_DISK);

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
        foreach (['portal_accounts.view', 'portal_accounts.manage'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $this->staff->givePermissionTo(['portal_accounts.view', 'portal_accounts.manage']);

        $this->najla = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927']);
        $this->saud = Owner::create(['name' => 'سعود', 'national_id' => '1041721885']);
        $plan = Plan::create(['plan_no' => '2516']);
        $this->najlasParcel = $this->parcel($plan, '1066', $this->najla);
        $this->saudsParcel = $this->parcel($plan, '1308', $this->saud);

        $this->office = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي');
        $this->access->setIdentity($this->office, PortalAccount::KIND_COMPANY, 'مكتب ركن الشمال الذهبي للعقارات', '7003374043', $this->staff);
        foreach ([$this->najla, $this->saud] as $owner) {
            $this->access->grant($this->office, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        }
    }

    public function test_the_company_reads_all_its_owners_land_on_the_usual_pages(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $this->get(route('portal.dashboard'))->assertOk()->assertSee('مكتب ركن الشمال الذهبي للعقارات');
        $this->get(route('portal.parcels.index'))->assertOk()->assertSee('1066')->assertSee('1308');

        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.31 24.7, 46.31 24.71, 46.3 24.71, 46.3 24.7)))', 4326)");
        $features = $this->getJson(route('portal.geo.parcels'))->assertOk()->json('features');
        $this->assertEqualsCanonicalizing(['1066', '1308'], array_map(fn (array $f): string => (string) $f['properties']['parcel_no'], $features));
    }

    public function test_the_overview_explorer_carries_each_owner_and_each_parcel_once(): void
    {
        // Saud and Najla share a third parcel half each.
        $shared = Parcel::create(['parcel_no' => '2018', 'geo_id' => 'geo-2018', 'plan_id' => $this->najlasParcel->plan_id]);
        $deed = Deed::create(['parcel_id' => $shared->id, 'deed_no' => 'D2018', 'deed_area' => 750, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach([$this->najla->id => ['ownership_share' => 50], $this->saud->id => ['ownership_share' => 50]]);

        $explorer = $this->actingAsOwner($this->najla, $this->office)->get(route('portal.overview'))
            ->assertOk()
            ->assertSee('7003374043')
            ->viewData('explorer');

        $this->assertSame(['نجلاء', 'سعود'], array_column($explorer['owners'], 'name'));
        $this->assertCount(3, $explorer['parcels']);
        $row = collect($explorer['parcels'])->firstWhere('parcel_no', '2018');
        $this->assertCount(2, $row['owners']);
        $this->assertEqualsWithDelta(750.0, $row['area'], 0.01);
    }

    public function test_a_parcel_of_another_owner_opens_as_the_one_who_holds_it(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $url = route('portal.parcels.show', $this->saudsParcel);
        $this->get($url)->assertRedirect($url);
        $this->assertSame($this->saud->id, auth('owner')->id());
        $this->get($url)->assertOk();
    }

    public function test_an_owner_who_withheld_documents_stays_out_of_the_shared_list(): void
    {
        $this->access->setPermissions($this->grant($this->saud), [PortalPermission::Documents->value => false], $this->staff);
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('d/saud.pdf', '%PDF');
        $saudsDoc = ParcelPhoto::create(['parcel_id' => $this->saudsParcel->id, 'photo_url' => 'd/saud.pdf', 'storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_type' => 'صك', 'status' => ParcelPhoto::STATUS_APPROVED]);
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('d/najla.pdf', '%PDF');
        $najlasDoc = ParcelPhoto::create(['parcel_id' => $this->najlasParcel->id, 'photo_url' => 'd/najla.pdf', 'storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_type' => 'صك', 'status' => ParcelPhoto::STATUS_APPROVED]);

        $this->actingAsOwner($this->najla, $this->office);
        $this->get(route('portal.documents.download', $najlasDoc))->assertOk();
        $this->get(route('portal.documents.download', $saudsDoc))->assertNotFound();
    }

    public function test_an_owner_on_their_own_still_sees_only_their_land(): void
    {
        $this->actingAsOwner($this->najla);

        $this->get(route('portal.dashboard'))->assertOk();
        $this->get(route('portal.parcels.show', $this->saudsParcel))->assertNotFound();
    }

    public function test_staff_turn_off_selling_for_one_owner_only(): void
    {
        Livewire::actingAs($this->staff)->test(AccountIndex::class)
            ->call('openSettings', $this->office->id)
            ->set('settingsPerms.'.$this->grant($this->najla)->id.'.'.PortalPermission::Sell->value, false)
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame(['sell' => false], $this->grant($this->najla)->permissions);
        $this->assertNull($this->grant($this->saud)->permissions);

        $this->actingAsOwner($this->najla, $this->office)
            ->post(route('portal.parcels.listing.store', $this->najlasParcel), ['contact_phone' => '0536664026'])
            ->assertForbidden();
        $this->get(route('portal.parcels.show', $this->najlasParcel))->assertOk()->assertDontSee(__('listings.offer_submit'));

        // Saud's land is untouched by Najla's choice.
        $this->actingAsOwner($this->saud, $this->office)
            ->post(route('portal.parcels.listing.store', $this->saudsParcel), ['contact_phone' => '0536664026'])
            ->assertRedirect();
    }

    public function test_a_company_whose_number_is_on_the_owners_record_is_still_an_agent(): void
    {
        // The company's phone saved as the owner's own: the grant reads "self", but the company is still bound by its permissions.
        $grant = $this->grant($this->najla);
        $grant->update(['relation' => PortalRelation::Self_]);
        $this->access->setPermissions($grant, [PortalPermission::Sell->value => false], $this->staff);

        $this->assertFalse($grant->fresh()->allows(PortalPermission::Sell));
        $this->actingAsOwner($this->najla, $this->office)
            ->post(route('portal.parcels.listing.store', $this->najlasParcel), ['contact_phone' => '0536664026'])
            ->assertForbidden();
    }

    public function test_the_app_obeys_the_same_permissions(): void
    {
        $this->access->setPermissions($this->grant($this->saud), [PortalPermission::RequestChanges->value => false], $this->staff);

        $this->apiAsOwner($this->saud, $this->office)
            ->postJson(route('api.modification-requests.store'), ['parcel_id' => $this->saudsParcel->id, 'field_name' => 'asset_type', 'new_value' => 'أرض'])
            ->assertForbidden();
    }

    public function test_a_commercial_registration_is_ten_digits(): void
    {
        Livewire::actingAs($this->staff)->test(AccountIndex::class)
            ->call('openSettings', $this->office->id)
            ->set('settingsCr', '12AB')
            ->call('saveSettings')
            ->assertHasErrors('settingsCr');
    }

    private function grant(Owner $owner): PortalAccountOwner
    {
        return PortalAccountOwner::query()->where('portal_account_id', $this->office->id)->where('owner_id', $owner->id)->firstOrFail();
    }

    private function parcel(Plan $plan, string $no, Owner $owner): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => $no, 'geo_id' => 'geo-'.$no, 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'D'.$no, 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach($owner->id);

        return $parcel;
    }
}
