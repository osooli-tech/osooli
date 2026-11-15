<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\OwnerPortfolio;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owners add photos of their own parcels, which wait for staff review; and
 * every owner with land sees it as portfolios, even one who never split it.
 */
class OwnerPhotosAndPortfoliosTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    private Owner $stranger;

    private Parcel $parcel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ParcelPhoto::PRIVATE_DISK);

        $this->owner = Owner::create(['name' => 'صاحب الأرض', 'national_id' => '1000000021', 'phone' => '0500000021']);
        $this->stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000022', 'phone' => '0500000022']);

        $plan = Plan::create(['plan_no' => '55']);
        $this->parcel = Parcel::create(['parcel_no' => '7001', 'geo_id' => 'geo-7001', 'plan_id' => $plan->id]);
        $second = Parcel::create(['parcel_no' => '7002', 'geo_id' => 'geo-7002', 'plan_id' => $plan->id]);
        foreach ([$this->parcel, $second] as $i => $parcel) {
            $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => '88800'.$i, 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value]);
            $deed->owners()->attach($this->owner->id);
        }
        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.31 24.7, 46.31 24.71, 46.3 24.71, 46.3 24.7)))', 4326)");
    }

    public function test_an_owner_uploads_photos_that_wait_for_review(): void
    {
        $this->actingAsOwner($this->owner)
            ->post(route('portal.parcels.photos.store', $this->parcel->id), [
                'photos' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('side.png')],
            ])
            ->assertRedirect(route('portal.parcels.show', $this->parcel->id));

        $photos = ParcelPhoto::query()->forReview()->where('parcel_id', $this->parcel->id)->get();
        $this->assertCount(2, $photos);
        $this->assertTrue($photos->every(fn (ParcelPhoto $p) => $p->status === ParcelPhoto::STATUS_PENDING
            && $p->uploaded_by_owner === $this->owner->id
            && $p->photo_type === PhotoType::Ground));

        // Listed for the owner as under review.
        $this->get(route('portal.parcels.show', $this->parcel->id))->assertOk()->assertSee('front.jpg')->assertSee(__('portal.photo_pending'));
    }

    public function test_only_images_on_ones_own_parcel_are_accepted(): void
    {
        $this->actingAsOwner($this->stranger)
            ->post(route('portal.parcels.photos.store', $this->parcel->id), ['photos' => [UploadedFile::fake()->image('x.jpg')]])
            ->assertNotFound();

        $this->actingAsOwner($this->owner)
            ->post(route('portal.parcels.photos.store', $this->parcel->id), ['photos' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0', null, 'photos');

        $this->assertSame(0, ParcelPhoto::query()->forReview()->count());
    }

    public function test_the_app_uploads_the_same_way(): void
    {
        $this->apiAsOwner($this->owner);

        $this->post(route('api.parcels.photos.store', $this->parcel->id), ['photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.photos.0.status', 'pending');

        $this->apiAsOwner($this->stranger);
        $this->post(route('api.parcels.photos.store', $this->parcel->id), ['photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_an_owner_who_never_split_their_land_sees_one_portfolio_in_their_name(): void
    {
        $this->actingAsOwner($this->owner)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee(__('portal.portfolios_title'))
            ->assertSee(__('portal.linked_portfolio_of', ['name' => 'صاحب الأرض']));
    }

    public function test_parcels_left_out_of_the_named_portfolios_get_one_of_their_own(): void
    {
        $portfolio = OwnerPortfolio::create(['owner_id' => $this->owner->id, 'name' => 'محفظة الرياض']);
        DB::table('owner_portfolio_parcels')->insert(['owner_id' => $this->owner->id, 'owner_portfolio_id' => $portfolio->id, 'parcel_id' => $this->parcel->id]);

        $this->actingAsOwner($this->owner)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('محفظة الرياض')
            ->assertSee(__('portal.portfolio_unassigned'))
            ->assertDontSee(__('portal.linked_portfolio_of', ['name' => 'صاحب الأرض']));
    }

    public function test_choosing_a_portfolio_narrows_the_home_to_its_parcels(): void
    {
        $portfolio = OwnerPortfolio::create(['owner_id' => $this->owner->id, 'name' => 'محفظة الرياض']);
        DB::table('owner_portfolio_parcels')->insert(['owner_id' => $this->owner->id, 'owner_portfolio_id' => $portfolio->id, 'parcel_id' => $this->parcel->id]);
        $this->actingAsOwner($this->owner);

        $named = $this->get(route('portal.dashboard', ['portfolio' => 'p'.$portfolio->id]))->assertOk();
        $this->assertSame(1, $named->viewData('portfolio')['total_parcels']);
        $this->assertSame(['7001'], array_map(fn (array $f): string => (string) $f['properties']['parcel_no'],
            $this->getJson(route('portal.geo.parcels', ['portfolio' => 'p'.$portfolio->id]))->json('features')));

        $rest = $this->get(route('portal.dashboard', ['portfolio' => 'rest']))->assertOk();
        $this->assertSame(1, $rest->viewData('portfolio')['total_parcels']);
        $this->assertSame(['7002'], array_map(fn (array $f): string => (string) $f['properties']['parcel_no'],
            $this->getJson(route('portal.geo.parcels', ['portfolio' => 'rest']))->json('features')));

        // Someone else's portfolio narrows nothing.
        $theirs = OwnerPortfolio::create(['owner_id' => $this->stranger->id, 'name' => 'ليست لك']);
        $this->assertNull($this->get(route('portal.dashboard', ['portfolio' => 'p'.$theirs->id]))->viewData('activePortfolio'));
    }
}
