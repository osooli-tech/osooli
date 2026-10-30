<?php

declare(strict_types=1);

namespace Tests\Feature\Listings;

use App\Enums\DeedStatus;
use App\Enums\InquiryStatus;
use App\Enums\ListingStatus;
use App\Livewire\Listings\ListingIndex;
use App\Models\Deed;
use App\Models\ListingInquiry;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelListing;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * An owner offers a parcel for sale; staff approve it; other owners then see
 * the parcel's shape, area and place with the seller's phone — never the
 * price, the deed or the seller's name — unless staff hid it from them.
 */
class ParcelListingTest extends TestCase
{
    use RefreshDatabase;

    private Owner $seller;

    private Owner $viewer;

    private Owner $partner;

    private Parcel $parcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = Owner::create(['name' => 'البائع الأول', 'national_id' => '1000000011', 'phone' => '0500000011']);
        $this->partner = Owner::create(['name' => 'الشريك', 'national_id' => '1000000012', 'phone' => '0500000012']);
        $this->viewer = Owner::create(['name' => 'المتصفح', 'national_id' => '1000000013', 'phone' => '0500000013']);

        $plan = Plan::create(['plan_no' => '77']);
        $this->parcel = Parcel::create(['parcel_no' => '9911', 'geo_id' => 'geo-9911', 'plan_id' => $plan->id, 'parcel_price' => 750000]);
        $deed = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '444555', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach([$this->seller->id, $this->partner->id]);

        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326)");
    }

    public function test_an_owner_asks_and_nobody_sees_it_before_approval(): void
    {
        $this->actingAs($this->seller, 'owner')
            ->post(route('portal.parcels.listing.store', $this->parcel->id), ['contact_phone' => '0555 123 456'])
            ->assertRedirect(route('portal.parcels.show', $this->parcel->id));

        $listing = ParcelListing::sole();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertSame('0555123456', $listing->contact_phone);

        $this->actingAs($this->viewer, 'owner')->get(route('portal.listings.index'))->assertOk()->assertDontSee('0555123456');
        $this->get(route('portal.listings.show', $listing->id))->assertNotFound();
    }

    public function test_only_a_holder_may_list_and_only_once_at_a_time(): void
    {
        $this->actingAs($this->viewer, 'owner')
            ->post(route('portal.parcels.listing.store', $this->parcel->id), ['contact_phone' => '0555123456'])
            ->assertNotFound();

        // A co-owner may ask; a second open listing on the same parcel is refused.
        $this->actingAs($this->partner, 'owner')
            ->post(route('portal.parcels.listing.store', $this->parcel->id), ['contact_phone' => '0555123456'])
            ->assertRedirect();
        $this->actingAs($this->seller, 'owner')
            ->post(route('portal.parcels.listing.store', $this->parcel->id), ['contact_phone' => '0555000000'])
            ->assertSessionHasErrors('contact_phone', null, 'listing');

        $this->assertSame(1, ParcelListing::count());
    }

    public function test_an_approved_listing_shows_the_parcel_but_no_price_deed_name_or_number(): void
    {
        $listing = $this->approvedListing();

        $this->actingAs($this->viewer, 'owner')->get(route('portal.listings.index'))
            ->assertOk()->assertSee('600')->assertDontSee('البائع الأول')->assertDontSee('0555123456');

        $this->get(route('portal.listings.show', $listing->id))
            ->assertOk()
            ->assertSee(__('listings.interest_submit'))
            ->assertDontSee('0555123456')
            ->assertDontSee('0500000011')
            ->assertDontSee('wa.me', false)
            ->assertDontSee('750,000')
            ->assertDontSee('444555')
            ->assertDontSee('9911')
            ->assertDontSee('البائع الأول')
            ->assertDontSee('الشريك');
    }

    public function test_the_api_shows_the_same_and_nothing_more(): void
    {
        $listing = $this->approvedListing();
        Sanctum::actingAs($this->viewer, [], 'sanctum');

        $item = $this->getJson(route('api.listings.show', $listing->id))->assertOk()->json('data');

        $this->assertSame(600.0, (float) $item['area']);
        $this->assertSame('MultiPolygon', $item['geometry']['type']);
        $this->assertEqualsCanonicalizing(
            ['id', 'area', 'asset_type', 'district', 'city', 'frontage', 'streets', 'my_interest', 'is_mine', 'listed_at', 'geometry'],
            array_keys($item),
        );
        $this->assertStringNotContainsString('0555123456', (string) json_encode($item));
        $this->getJson(route('api.listings.index'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_hidden_owner_does_not_see_it_anywhere(): void
    {
        $listing = $this->approvedListing();
        $listing->blockedOwners()->attach($this->viewer->id);

        $this->actingAs($this->viewer, 'owner')->get(route('portal.listings.index'))->assertOk()->assertDontSee('0555123456');
        $this->get(route('portal.listings.show', $listing->id))->assertNotFound();

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->getJson(route('api.listings.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('api.listings.show', $listing->id))->assertNotFound();
    }

    public function test_the_holders_see_their_own_listing_marked_and_cannot_ask_about_it(): void
    {
        $listing = $this->approvedListing();

        foreach ([$this->seller, $this->partner] as $holder) {
            Sanctum::actingAs($holder, [], 'sanctum');
            $this->getJson(route('api.listings.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_mine', true);
            $this->postJson(route('api.listings.interest', $listing->id))->assertUnprocessable();
        }

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->getJson(route('api.listings.index'))->assertJsonPath('data.0.is_mine', false);

        $this->actingAs($this->seller, 'owner')->get(route('portal.listings.show', $listing->id))
            ->assertOk()
            ->assertSee(__('listings.yours_hint'))
            ->assertDontSee(__('listings.interest_submit'));

        Sanctum::actingAs($this->seller, [], 'sanctum');
        $this->getJson(route('api.listings.mine'))->assertOk()->assertJsonPath('data.0.status', 'approved');
        $this->assertSame(0, ListingInquiry::count());
    }

    public function test_a_parcel_sold_since_drops_out_on_its_own(): void
    {
        $this->approvedListing();

        $buyer = Owner::create(['name' => 'المشتري', 'national_id' => '1000000014']);
        $newDeed = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '444556', 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        $newDeed->owners()->attach($buyer->id);

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->getJson(route('api.listings.index'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_seller_withdraws_and_nobody_else_can(): void
    {
        $listing = $this->approvedListing();

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->deleteJson(route('api.listings.destroy', $listing->id))->assertNotFound();

        Sanctum::actingAs($this->seller, [], 'sanctum');
        $this->deleteJson(route('api.listings.destroy', $listing->id))->assertOk();
        $this->assertSame(ListingStatus::Withdrawn, $listing->fresh()?->status);

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->getJson(route('api.listings.index'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_review_needs_its_permissions(): void
    {
        $listing = ParcelListing::create(['parcel_id' => $this->parcel->id, 'owner_id' => $this->seller->id, 'contact_phone' => '0555123456', 'status' => ListingStatus::Pending]);

        $nobody = User::factory()->create(['is_active' => true]);
        $this->actingAs($nobody)->get(route('listings.index'))->assertForbidden();

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(Permission::findOrCreate('listings.view', 'web'));
        Livewire::actingAs($viewer)->test(ListingIndex::class)
            ->call('openListing', $listing->id)
            ->assertSee('الشريك')
            ->call('approve')
            ->assertForbidden();
        $this->assertSame(ListingStatus::Pending, $listing->fresh()?->status);
    }

    public function test_a_manager_rejects_with_a_reason_approves_and_hides_from_an_owner(): void
    {
        $listing = ParcelListing::create(['parcel_id' => $this->parcel->id, 'owner_id' => $this->seller->id, 'contact_phone' => '0555123456', 'status' => ListingStatus::Pending]);
        $manager = User::factory()->create(['is_active' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('listings.view', 'web'), Permission::findOrCreate('listings.manage', 'web'));

        Livewire::actingAs($manager)->test(ListingIndex::class)
            ->call('openListing', $listing->id)
            ->call('reject')
            ->assertHasErrors('rejectionReason')
            ->call('approve')
            ->call('openListing', $listing->id)
            ->set('ownerSearch', 'المتصفح')
            ->assertSee('0500000013')
            ->call('block', $this->viewer->id);

        $listing->refresh();
        $this->assertSame(ListingStatus::Approved, $listing->status);
        $this->assertSame($manager->id, $listing->reviewed_by);
        $this->assertTrue($listing->blockedOwners()->whereKey($this->viewer->id)->exists());
    }

    public function test_a_buyer_asks_sukooki_to_put_them_in_touch_once(): void
    {
        $listing = $this->approvedListing();

        $this->actingAs($this->viewer, 'owner')
            ->post(route('portal.listings.interest', $listing->id), ['note' => 'أبغى أعرف السعر'])
            ->assertRedirect(route('portal.listings.show', $listing->id));
        $this->post(route('portal.listings.interest', $listing->id), ['note' => 'مرة ثانية']);

        $inquiry = ListingInquiry::sole();
        $this->assertSame($this->viewer->id, $inquiry->owner_id);
        $this->assertSame(InquiryStatus::New, $inquiry->status);
        $this->assertSame('أبغى أعرف السعر', $inquiry->note);

        $this->get(route('portal.listings.show', $listing->id))
            ->assertSee(__('listings.interest_status', ['status' => InquiryStatus::New->label()]))
            ->assertDontSee('0555123456');

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->getJson(route('api.listings.show', $listing->id))->assertJsonPath('data.my_interest', 'new');
    }

    public function test_a_hidden_owner_cannot_ask_and_the_api_asks_the_same_way(): void
    {
        $listing = $this->approvedListing();
        $hidden = Owner::create(['name' => 'ممنوع', 'national_id' => '1000000015', 'phone' => '0500000015']);
        $listing->blockedOwners()->attach($hidden->id);

        Sanctum::actingAs($hidden, [], 'sanctum');
        $this->postJson(route('api.listings.interest', $listing->id))->assertNotFound();

        Sanctum::actingAs($this->viewer, [], 'sanctum');
        $this->postJson(route('api.listings.interest', $listing->id), ['note' => 'مهتم'])->assertCreated()->assertJsonPath('data.status', 'new');
        $this->assertSame(1, ListingInquiry::count());
    }

    public function test_staff_see_the_whole_parcel_and_the_buyer_and_mark_the_request(): void
    {
        $listing = $this->approvedListing();
        $inquiry = ListingInquiry::create(['parcel_listing_id' => $listing->id, 'owner_id' => $this->viewer->id, 'status' => InquiryStatus::New]);
        $manager = User::factory()->create(['is_active' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('listings.view', 'web'), Permission::findOrCreate('listings.manage', 'web'));

        Livewire::actingAs($manager)->test(ListingIndex::class)
            ->set('statusFilter', 'all')
            ->call('openListing', $listing->id)
            ->assertSee('444555')
            ->assertSee('750,000')
            ->assertSee('0500000013')
            ->call('markInquiry', $inquiry->id, 'contacted');

        $this->assertSame(InquiryStatus::Contacted, $inquiry->fresh()?->status);
        $this->assertSame($manager->id, $inquiry->fresh()?->handled_by);
    }

    public function test_the_review_map_shows_every_listing_with_its_status_and_waiting_buyers(): void
    {
        $listing = $this->approvedListing();
        ListingInquiry::create(['parcel_listing_id' => $listing->id, 'owner_id' => $this->viewer->id, 'status' => InquiryStatus::New]);
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->givePermissionTo(Permission::findOrCreate('listings.view', 'web'));

        $map = Livewire::actingAs($viewer)->test(ListingIndex::class)->instance()->map();

        $this->assertCount(1, $map['features']);
        $this->assertSame('approved', $map['features'][0]['properties']['status']);
        $this->assertSame(1, $map['features'][0]['properties']['new_inquiries']);
        $this->assertSame('9911', $map['features'][0]['properties']['parcel_no']);
    }

    public function test_the_market_map_carries_only_the_popup_details(): void
    {
        $this->approvedListing();

        $this->actingAs($this->viewer, 'owner')->get(route('portal.listings.index'))
            ->assertOk()
            ->assertViewHas('map', fn (array $map): bool => count($map['features']) === 1
                && array_keys($map['features'][0]['properties']) === ['id', 'area', 'asset_type', 'place', 'streets', 'is_mine']);
    }

    private function approvedListing(): ParcelListing
    {
        return ParcelListing::create([
            'parcel_id' => $this->parcel->id,
            'owner_id' => $this->seller->id,
            'contact_phone' => '0555123456',
            'status' => ListingStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }
}
