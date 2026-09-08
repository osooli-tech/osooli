<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeedStatus;
use App\Livewire\Dashboard\KpiCards;
use App\Livewire\Owners\OwnerIndex;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Support\OwnerScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A user with no scope rows must see exactly what they see today (every
 * assertion here has an "unrestricted still sees everything" counterpart,
 * not just "restriction works") — this feature is opt-in, and a regression
 * that silently restricted every existing account would be worse than the
 * feature not existing at all.
 */
class OwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private Owner $scopedOwner;

    private Owner $otherOwner;

    private Parcel $scopedParcel;

    private Parcel $otherParcel;

    private User $restrictedUser;

    private User $unrestrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '1', 'district_id' => $district->id]);

        $this->scopedOwner = Owner::create(['name' => 'دواجن الوطنية', 'national_id' => '7001785448']);
        $this->otherOwner = Owner::create(['name' => 'مالك آخر', 'national_id' => '1000000002']);

        $this->scopedParcel = $this->makeParcel($plan->id, '1', $this->scopedOwner);
        $this->otherParcel = $this->makeParcel($plan->id, '2', $this->otherOwner);

        $this->restrictedUser = User::create([
            'name' => 'مهندس المشروع', 'email' => 'engineer@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $this->restrictedUser->scopedOwners()->attach($this->scopedOwner->id);

        $this->unrestrictedUser = User::create([
            'name' => 'مدير', 'email' => 'manager@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
    }

    // ── OwnerScope helper ──────────────────────────────────────────

    public function test_a_user_with_no_scope_rows_is_unrestricted(): void
    {
        $this->assertFalse(OwnerScope::isRestricted($this->unrestrictedUser));
        $this->assertNull(OwnerScope::ownerIds($this->unrestrictedUser));
        $this->assertNull(OwnerScope::parcelIds($this->unrestrictedUser));
        $this->assertTrue(OwnerScope::canSeeParcel($this->unrestrictedUser, $this->otherParcel->id));
    }

    public function test_a_guest_is_unrestricted(): void
    {
        $this->assertFalse(OwnerScope::isRestricted(null));
        $this->assertNull(OwnerScope::parcelIds(null));
    }

    public function test_a_scoped_user_only_resolves_their_owners_parcel(): void
    {
        $this->assertTrue(OwnerScope::isRestricted($this->restrictedUser));
        $this->assertSame([$this->scopedOwner->id], OwnerScope::ownerIds($this->restrictedUser));
        $this->assertSame([$this->scopedParcel->id], OwnerScope::parcelIds($this->restrictedUser));
        $this->assertTrue(OwnerScope::canSeeParcel($this->restrictedUser, $this->scopedParcel->id));
        $this->assertFalse(OwnerScope::canSeeParcel($this->restrictedUser, $this->otherParcel->id));
    }

    // ── Map GeoJSON feed ───────────────────────────────────────────

    public function test_the_map_feed_shows_every_parcel_to_an_unrestricted_user(): void
    {
        $this->actingAs($this->unrestrictedUser)
            ->getJson(route('geo.parcels'))
            ->assertOk()
            ->assertJsonCount(2, 'features');
    }

    public function test_the_map_feed_shows_only_the_scoped_parcel_to_a_restricted_user(): void
    {
        $response = $this->actingAs($this->restrictedUser)
            ->getJson(route('geo.parcels'))
            ->assertOk()
            ->assertJsonCount(1, 'features');

        $this->assertSame(
            $this->scopedParcel->id,
            $response->json('features.0.properties.id')
        );
    }

    // ── Owners list ────────────────────────────────────────────────

    public function test_the_owners_list_shows_everyone_to_an_unrestricted_user(): void
    {
        $names = Livewire::actingAs($this->unrestrictedUser)
            ->test(OwnerIndex::class)
            ->viewData('owners')
            ->pluck('name');

        $this->assertTrue($names->contains('دواجن الوطنية'));
        $this->assertTrue($names->contains('مالك آخر'));
    }

    public function test_the_owners_list_shows_only_the_scoped_owner_to_a_restricted_user(): void
    {
        $names = Livewire::actingAs($this->restrictedUser)
            ->test(OwnerIndex::class)
            ->viewData('owners')
            ->pluck('name');

        $this->assertSame(['دواجن الوطنية'], $names->all());
    }

    // ── Parcel pages ───────────────────────────────────────────────

    public function test_an_unrestricted_user_can_open_any_parcel(): void
    {
        $this->actingAs($this->unrestrictedUser)
            ->get(route('parcels.show', $this->otherParcel))
            ->assertOk();
    }

    public function test_a_restricted_user_can_open_their_own_scoped_parcel(): void
    {
        $this->actingAs($this->restrictedUser)
            ->get(route('parcels.show', $this->scopedParcel))
            ->assertOk();

        $this->actingAs($this->restrictedUser)
            ->get(route('parcels.twin', $this->scopedParcel))
            ->assertOk();
    }

    public function test_a_restricted_user_is_forbidden_from_a_parcel_outside_their_scope(): void
    {
        $this->actingAs($this->restrictedUser)
            ->get(route('parcels.show', $this->otherParcel))
            ->assertForbidden();

        $this->actingAs($this->restrictedUser)
            ->get(route('parcels.twin', $this->otherParcel))
            ->assertForbidden();
    }

    // ── Dashboard KPI counts ───────────────────────────────────────

    public function test_the_kpi_cards_count_only_the_scoped_parcel_for_a_restricted_user(): void
    {
        Livewire::actingAs($this->restrictedUser)
            ->test(KpiCards::class)
            ->assertSet('totalParcels', 1)
            ->assertSet('totalOwners', 1);
    }

    public function test_the_kpi_cards_count_everything_for_an_unrestricted_user(): void
    {
        Livewire::actingAs($this->unrestrictedUser)
            ->test(KpiCards::class)
            ->assertSet('totalParcels', 2)
            ->assertSet('totalOwners', 2);
    }

    // ── Artisan commands ───────────────────────────────────────────

    public function test_the_scope_command_restricts_a_user(): void
    {
        $fresh = User::create([
            'name' => 'مستخدم جديد', 'email' => 'fresh@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        Artisan::call('app:scope-user-to-owner', [
            'email' => 'fresh@sakuki.test',
            'national_id' => $this->scopedOwner->national_id,
        ]);

        $this->assertTrue(OwnerScope::isRestricted($fresh->fresh()));
        $this->assertSame([$this->scopedOwner->id], OwnerScope::ownerIds($fresh->fresh()));
    }

    public function test_the_unscope_command_removes_every_restriction(): void
    {
        Artisan::call('app:unscope-user', ['email' => $this->restrictedUser->email]);

        $this->assertFalse(OwnerScope::isRestricted($this->restrictedUser->fresh()));
    }

    public function test_the_scope_command_fails_cleanly_for_an_unknown_owner(): void
    {
        $exitCode = Artisan::call('app:scope-user-to-owner', [
            'email' => $this->unrestrictedUser->email,
            'national_id' => 'no-such-id',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertFalse(OwnerScope::isRestricted($this->unrestrictedUser->fresh()));
    }

    private function makeParcel(int $planId, string $parcelNo, Owner $owner): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => 'geo-'.$parcelNo,
            'plan_id' => $planId,
            'm_price' => 1500,
        ]);

        DB::update(
            "UPDATE parcels SET geom = ST_SetSRID(ST_GeomFromText(
                'MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))'
             ), 4326) WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => 'deed-'.$parcelNo,
            'deed_area' => 1000,
            'deed_status' => DeedStatus::Updated->value,
        ]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
