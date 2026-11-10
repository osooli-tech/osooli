<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelListing;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One account acting for several owners: switching between them, and what a
 * view-only grant may not do.
 */
class SwitchOwnerTest extends TestCase
{
    use RefreshDatabase;

    private PortalAccessService $access;

    private User $staff;

    private Owner $najla;

    private Owner $saud;

    private Owner $stranger;

    private PortalAccount $office;

    private Parcel $najlasParcel;

    private Parcel $saudsParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
        $this->najla = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927']);
        $this->saud = Owner::create(['name' => 'سعود', 'national_id' => '1041721885']);
        $this->stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000009']);

        $plan = Plan::create(['plan_no' => '2516']);
        $this->najlasParcel = $this->parcel($plan, '1066', $this->najla);
        $this->saudsParcel = $this->parcel($plan, '1308', $this->saud);

        $this->office = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي');
        $this->access->grant($this->office, $this->najla, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($this->office, $this->saud, PortalRelation::Delegate, false, PortalGrantSource::Staff, $this->staff);
    }

    public function test_switching_to_a_granted_owner_changes_the_owner_guard(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $this->post(route('portal.switch', $this->saud))->assertRedirect(route('portal.dashboard'));

        $this->assertSame($this->saud->id, Auth::guard('owner')->id());
        $this->assertSame($this->saud->id, $this->office->fresh()->last_owner_id);
        $this->get(route('portal.parcels.show', $this->saudsParcel))->assertOk();
    }

    public function test_switching_with_one_of_the_owners_parcels_opens_it(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $this->post(route('portal.switch', $this->saud), ['parcel' => $this->saudsParcel->id])
            ->assertRedirect(route('portal.parcels.show', $this->saudsParcel));

        // A parcel that is not the new owner's is not followed.
        $this->post(route('portal.switch', $this->saud), ['parcel' => $this->najlasParcel->id])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_switching_to_an_owner_without_a_grant_is_forbidden(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $this->post(route('portal.switch', $this->stranger))->assertForbidden();

        $this->assertSame($this->najla->id, Auth::guard('owner')->id());
    }

    public function test_switching_to_a_pending_grant_is_forbidden(): void
    {
        $this->access->grant($this->office, $this->stranger, PortalRelation::Delegate, true, PortalGrantSource::Import, null, needsReview: true);
        $this->actingAsOwner($this->najla, $this->office);

        $this->post(route('portal.switch', $this->stranger))->assertForbidden();
    }

    public function test_after_switching_another_owners_parcel_is_still_not_found(): void
    {
        $this->actingAsOwner($this->najla, $this->office);
        $this->post(route('portal.switch', $this->saud));

        // Acting for Saud now: Najla's parcel is hers, not his.
        $this->get(route('portal.parcels.show', $this->najlasParcel))->assertNotFound();
    }

    public function test_view_only_cannot_list_upload_request_or_show_interest(): void
    {
        Storage::fake('public');
        $listing = ParcelListing::create(['parcel_id' => $this->najlasParcel->id, 'owner_id' => $this->najla->id, 'contact_phone' => '0500000000', 'status' => 'approved']);
        $this->actingAsOwner($this->saud, $this->office);

        $this->post(route('portal.parcels.listing.store', $this->saudsParcel), ['contact_phone' => '0536664026'])->assertForbidden();
        $this->post(route('portal.parcels.photos.store', $this->saudsParcel), ['photo' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->post(route('portal.parcels.modification-requests.store', $this->saudsParcel), ['field' => 'x'])->assertForbidden();
        $this->post(route('portal.listings.interest', $listing))->assertForbidden();
        $this->put(route('portal.profile.update'), ['name' => 'سعود'])->assertForbidden();
    }

    public function test_a_delegate_cannot_edit_the_owners_profile(): void
    {
        $this->actingAsOwner($this->najla, $this->office);

        $this->put(route('portal.profile.update'), ['name' => 'اسم آخر'])->assertForbidden();

        $this->assertSame('نجلاء', $this->najla->fresh()->name);
    }

    public function test_the_switcher_lists_every_usable_owner_and_hides_pending_ones(): void
    {
        $this->access->grant($this->office, $this->stranger, PortalRelation::Delegate, true, PortalGrantSource::Import, null, needsReview: true);

        $this->actingAsOwner($this->najla, $this->office)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee(route('portal.switch', $this->saud), false)
            ->assertDontSee(route('portal.switch', $this->stranger), false)
            ->assertSee('مكتب ركن الشمال الذهبي');
    }

    public function test_a_single_owner_account_sees_no_switcher(): void
    {
        $own = Owner::create(['name' => 'وحيد', 'national_id' => '1000000008', 'phone' => '0500000008']);

        $this->actingAsOwner($own)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertDontSee('portal/switch', false);
    }

    private function parcel(Plan $plan, string $no, Owner $owner): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => $no, 'geo_id' => $no.'-'.$plan->plan_no, 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => $no.'00', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach($owner->id);

        return $parcel;
    }
}
