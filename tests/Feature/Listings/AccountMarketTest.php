<?php

declare(strict_types=1);

namespace Tests\Feature\Listings;

use App\Enums\DeedStatus;
use App\Enums\ListingStatus;
use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Deed;
use App\Models\ListingInquiry;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelListing;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In the market, an account is one buyer: its own owners' listings are its
 * own, a block on one of its owners blocks it, and what it does is recorded
 * against it.
 */
class AccountMarketTest extends TestCase
{
    use RefreshDatabase;

    private PortalAccessService $access;

    private User $staff;

    private Owner $najla;

    private Owner $saud;

    private Owner $seller;

    private PortalAccount $office;

    private Parcel $najlasParcel;

    private Parcel $sellersParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
        $this->najla = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927']);
        $this->saud = Owner::create(['name' => 'سعود', 'national_id' => '1041721885']);
        $this->seller = Owner::create(['name' => 'بائع', 'national_id' => '1000000009', 'phone' => '0500000009']);

        $plan = Plan::create(['plan_no' => '2516']);
        $this->najlasParcel = $this->parcel($plan, '1066', $this->najla);
        $this->sellersParcel = $this->parcel($plan, '900', $this->seller);

        $this->office = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي');
        $this->access->grant($this->office, $this->najla, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($this->office, $this->saud, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
    }

    public function test_a_listing_of_any_owner_in_the_account_is_marked_mine(): void
    {
        $listing = $this->listing($this->najlasParcel, $this->najla);

        $this->apiAsOwner($this->saud, $this->office)
            ->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.is_mine', true);
    }

    public function test_interest_in_an_own_account_listing_is_refused(): void
    {
        $listing = $this->listing($this->najlasParcel, $this->najla);

        $this->actingAsOwner($this->saud, $this->office)
            ->post(route('portal.listings.interest', $listing))
            ->assertSessionHasErrorsIn('listing', 'note');

        $this->assertSame(0, ListingInquiry::count());
    }

    public function test_a_listing_blocked_for_one_owner_is_hidden_from_the_whole_account(): void
    {
        $listing = $this->listing($this->sellersParcel, $this->seller);
        $this->actingAsOwner($this->saud, $this->office)->get(route('portal.listings.show', $listing))->assertOk();

        $listing->blockedOwners()->attach($this->najla->id, ['created_by' => $this->staff->id]);

        // Saud is not blocked himself, but the account that acts for him also acts for Najla.
        $this->get(route('portal.listings.show', $listing))->assertNotFound();
    }

    public function test_actions_record_the_acting_account(): void
    {
        $listing = $this->listing($this->sellersParcel, $this->seller);
        $this->actingAsOwner($this->najla, $this->office);

        $this->post(route('portal.listings.interest', $listing));
        $this->post(route('portal.parcels.listing.store', $this->najlasParcel), ['contact_phone' => '0536664026']);
        $this->post(route('portal.parcels.modification-requests.store', $this->najlasParcel), ['field_name' => 'asset_type', 'new_value' => 'أرض']);

        $this->assertSame($this->office->id, ListingInquiry::firstOrFail()->portal_account_id);
        $this->assertSame($this->office->id, ParcelListing::where('parcel_id', $this->najlasParcel->id)->firstOrFail()->portal_account_id);
        $this->assertSame($this->office->id, ModificationRequest::firstOrFail()->portal_account_id);
    }

    public function test_staff_records_carry_no_account(): void
    {
        $listing = $this->listing($this->sellersParcel, $this->seller);

        $this->assertNull($listing->portal_account_id);
    }

    private function parcel(Plan $plan, string $no, Owner $owner): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => $no, 'geo_id' => $no.'-'.$plan->plan_no, 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => $no.'00', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach($owner->id);

        return $parcel;
    }

    private function listing(Parcel $parcel, Owner $owner): ParcelListing
    {
        return ParcelListing::create([
            'parcel_id' => $parcel->id, 'owner_id' => $owner->id, 'contact_phone' => '0500000000',
            'status' => ListingStatus::Approved, 'reviewed_at' => now(),
        ]);
    }
}
