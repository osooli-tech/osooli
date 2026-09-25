<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeedStatus;
use App\Livewire\Documents\DocumentUpload;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression coverage for the upload screen's parcel picker: parcel_no alone
 * repeats across plans, so the dropdown needs geo_id (always unique) both in
 * its search and in the option label, plus an owner filter to narrow a long
 * list. The owner filter must still compose with OwnerScope rather than
 * become a second, looser way to reach a parcel a restricted user can't see.
 */
class DocumentUploadFilterTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private Owner $firstOwner;

    private Owner $secondOwner;

    private Parcel $firstParcel;

    private Parcel $secondParcel;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $this->plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $this->firstOwner = Owner::create(['name' => 'مالك أول', 'national_id' => '1000000001']);
        $this->secondOwner = Owner::create(['name' => 'مالك ثاني', 'national_id' => '1000000002']);

        // Same parcel_no on purpose — the real-world case that made the
        // plain dropdown unusable.
        $this->firstParcel = $this->makeParcel('101', 'geo-101-a', $this->firstOwner);
        $this->secondParcel = $this->makeParcel('101', 'geo-101-b', $this->secondOwner);

        $this->user = User::create([
            'name' => 'مستخدم', 'email' => 'uploader@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        Permission::findOrCreate('documents.upload', 'web');
        $this->user->givePermissionTo('documents.upload');
    }

    public function test_the_parcel_options_disambiguate_a_repeated_parcel_number_with_its_geo_id(): void
    {
        $options = Livewire::actingAs($this->user)
            ->test(DocumentUpload::class)
            ->viewData('parcelOptions');

        $this->assertSame('101 — geo-101-a', $options[$this->firstParcel->id]);
        $this->assertSame('101 — geo-101-b', $options[$this->secondParcel->id]);
    }

    public function test_the_parcel_search_narrows_by_geo_id(): void
    {
        $options = Livewire::actingAs($this->user)
            ->test(DocumentUpload::class)
            ->set('parcelSearch', 'geo-101-b')
            ->viewData('parcelOptions');

        $this->assertSame([$this->secondParcel->id], array_keys($options));
    }

    public function test_the_parcel_search_narrows_by_parcel_number(): void
    {
        $thirdParcel = $this->makeParcel('202', 'geo-202', $this->firstOwner);

        $options = Livewire::actingAs($this->user)
            ->test(DocumentUpload::class)
            ->set('parcelSearch', '202')
            ->viewData('parcelOptions');

        $this->assertSame([$thirdParcel->id], array_keys($options));
    }

    public function test_the_owner_filter_narrows_the_parcel_list_to_that_owners_parcels(): void
    {
        $options = Livewire::actingAs($this->user)
            ->test(DocumentUpload::class)
            ->set('ownerId', $this->secondOwner->id)
            ->viewData('parcelOptions');

        $this->assertSame([$this->secondParcel->id], array_keys($options));
    }

    public function test_a_restricted_user_cannot_use_the_owner_filter_to_reach_a_parcel_outside_their_scope(): void
    {
        $this->user->scopedOwners()->attach($this->firstOwner->id);

        // Asking for the other owner's parcels must yield nothing, not a
        // bypass of the scope this user is otherwise held to everywhere else.
        $options = Livewire::actingAs($this->user)
            ->test(DocumentUpload::class)
            ->set('ownerId', $this->secondOwner->id)
            ->viewData('parcelOptions');

        $this->assertSame([], $options);
    }

    private function makeParcel(string $parcelNo, string $geoId, Owner $owner): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => $geoId,
            'plan_id' => $this->plan->id,
        ]);

        DB::update(
            "UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => 'deed-'.$geoId,
            'deed_area' => 1000,
            'deed_status' => DeedStatus::Updated->value,
        ]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
