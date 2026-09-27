<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Reference\MissingBoundariesPanel;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\Geo\MissingBoundaries;
use App\Support\Geo\ParcelPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * «الحدود الناقصة»: a district with no boundary drawn from its parcels, and
 * records added by name moved to the National Address one they lie in.
 *
 * Fixture: one region; two bounded cities side by side — العمارية (46.30–
 * 46.40) and الدرعية (46.50–46.60); a bounded district «الشمال» in العمارية.
 */
class MissingBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    private City $ammariyah;

    private City $diriyah;

    private District $north;

    public function test_a_district_is_drawn_from_its_parcels_inside_its_city_and_clear_of_its_neighbours(): void
    {
        $this->fixtures();
        $farms = District::create(['city_id' => $this->ammariyah->id, 'name_ar' => 'المزارع']);
        $a = $this->parcel($farms, 'A', 46.31, 24.81);
        $this->parcel($farms, 'B', 46.33, 24.82);
        $this->parcel($farms, 'C', 46.32, 24.835);

        $row = MissingBoundaries::row('districts', $farms->id);
        $this->assertSame('draw', $row['action'] ?? null);

        $this->actingAs($this->userWith(['boundaries.edit']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->assertSee('المزارع')
            ->call('draw', $farms->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('parcels', DB::table('districts')->where('id', $farms->id)->value('boundary_source'));
        $this->assertSame('ok', ParcelPlacement::forParcel($a->id)['status']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'district.geometry_derive', 'target_id' => $farms->id]);

        // Clear of «الشمال» (from 24.85 up), inside العمارية.
        $overlap = DB::selectOne('SELECT ST_Area(ST_Intersection(a.geom, b.geom)) AS x FROM districts a, districts b WHERE a.id = ? AND b.id = ?', [$farms->id, $this->north->id]);
        $this->assertLessThan(0.0000001, (float) $overlap->x);
        $outside = DB::selectOne('SELECT ST_Area(ST_Difference(d.geom, c.geom)) AS x FROM districts d, cities c WHERE d.id = ? AND c.id = ?', [$farms->id, $this->ammariyah->id]);
        $this->assertLessThan(0.0000001, (float) $outside->x);
    }

    public function test_a_district_filed_under_another_city_is_moved_before_it_is_drawn(): void
    {
        $this->fixtures();
        $ammariyah = District::create(['city_id' => $this->diriyah->id, 'name_ar' => 'العمارية']);
        $this->parcel($ammariyah, 'A', 46.31, 24.81);
        $this->parcel($ammariyah, 'B', 46.32, 24.82);

        $row = MissingBoundaries::row('districts', $ammariyah->id);
        $this->assertSame(['move', $this->ammariyah->id], [$row['action'] ?? null, $row['target_id'] ?? null]);
        $this->assertFalse(MissingBoundaries::drawDistrict($ammariyah->id));

        $this->actingAs($this->userWith(['boundaries.edit', 'parcels.placement_fix']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->call('move', 'districts', $ammariyah->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame($this->ammariyah->id, (int) DB::table('districts')->where('id', $ammariyah->id)->value('city_id'));
        $this->assertSame('draw', MissingBoundaries::row('districts', $ammariyah->id)['action'] ?? null);
    }

    public function test_a_district_lying_in_a_bounded_one_hands_its_plans_over(): void
    {
        $this->fixtures();
        $alias = District::create(['city_id' => $this->ammariyah->id, 'name_ar' => 'شمال العمارية']);
        $this->parcel($alias, 'A', 46.31, 24.86);

        $this->assertSame('merge', MissingBoundaries::row('districts', $alias->id)['action'] ?? null);

        $this->actingAs($this->userWith(['boundaries.edit', 'parcels.placement_fix']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->call('move', 'districts', $alias->id);

        $this->assertSame($this->north->id, (int) Plan::query()->where('plan_no', 'P-'.$alias->id)->value('district_id'));
    }

    public function test_a_city_and_a_region_added_by_name_move_their_children_to_the_bounded_ones(): void
    {
        $this->fixtures();
        $otherRegion = Region::create(['country_id' => $this->region->country_id, 'name_ar' => 'منطقة الرياض ']);
        $byName = City::create(['region_id' => $otherRegion->id, 'name_ar' => 'العمارية القديمة']);
        $district = District::create(['city_id' => $byName->id, 'name_ar' => 'الوسط']);
        $this->parcel($district, 'A', 46.35, 24.82);

        $this->assertSame(['merge', $this->ammariyah->id], [MissingBoundaries::row('cities', $byName->id)['action'] ?? null, MissingBoundaries::row('cities', $byName->id)['target_id'] ?? null]);
        $this->assertSame(['merge', $this->region->id], [MissingBoundaries::row('regions', $otherRegion->id)['action'] ?? null, MissingBoundaries::row('regions', $otherRegion->id)['target_id'] ?? null]);

        $this->actingAs($this->userWith(['boundaries.edit', 'parcels.placement_fix']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->call('move', 'regions', $otherRegion->id)
            ->call('move', 'cities', $byName->id);

        $this->assertSame($this->region->id, (int) DB::table('cities')->where('id', $byName->id)->value('region_id'));
        $this->assertSame($this->ammariyah->id, (int) DB::table('districts')->where('id', $district->id)->value('city_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'city.merge', 'target_id' => $byName->id]);
    }

    public function test_moving_needs_the_reassign_permission_and_the_panel_needs_boundaries_edit(): void
    {
        $this->fixtures();
        $ammariyah = District::create(['city_id' => $this->diriyah->id, 'name_ar' => 'العمارية']);
        $this->parcel($ammariyah, 'A', 46.31, 24.81);

        $this->actingAs($this->userWith(['boundaries.edit']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->assertDontSee('انقله إلى مدينة')
            ->call('move', 'districts', $ammariyah->id)
            ->assertForbidden();

        $this->actingAs($this->userWith(['reference.view']));
        Livewire::test(MissingBoundariesPanel::class)
            ->dispatch('missing-boundaries')
            ->assertForbidden();
    }

    private function fixtures(): void
    {
        app()->setLocale('ar');
        $country = Country::create(['name_ar' => 'السعودية', 'iso_code' => 'SA']);
        $this->region = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة الرياض']);
        $this->ammariyah = City::create(['region_id' => $this->region->id, 'name_ar' => 'العمارية']);
        $this->diriyah = City::create(['region_id' => $this->region->id, 'name_ar' => 'الدرعية']);
        $this->north = District::create(['city_id' => $this->ammariyah->id, 'name_ar' => 'الشمال']);

        $this->boundary('regions', $this->region->id, 45.0, 24.0, 2.0, 'official');
        $this->boundary('cities', $this->ammariyah->id, 46.30, 24.80, 0.1, 'official');
        $this->boundary('cities', $this->diriyah->id, 46.50, 24.80, 0.1, 'official');
        $this->boundary('districts', $this->north->id, 46.30, 24.85, 0.05, 'official');
    }

    private function parcel(District $district, string $number, float $lng, float $lat): Parcel
    {
        $plan = Plan::firstOrCreate(['plan_no' => 'P-'.$district->id], ['district_id' => $district->id]);
        $parcel = Parcel::create(['parcel_no' => $number, 'geo_id' => $number.'-'.$district->id, 'plan_id' => $plan->id]);
        $this->boundary('parcels', $parcel->id, $lng - 0.0002, $lat - 0.0002, 0.0004, null);

        return $parcel;
    }

    private function boundary(string $table, int $id, float $west, float $south, float $size, ?string $source): void
    {
        $ring = [[$west, $south], [$west + $size, $south], [$west + $size, $south + $size], [$west, $south + $size], [$west, $south]];
        DB::update(
            "UPDATE {$table} SET geom = ".Spatial::fromGeoJson().' WHERE id = ?',
            [json_encode(['type' => 'MultiPolygon', 'coordinates' => [[$ring]]]), $id]
        );

        if ($table !== 'parcels') {
            DB::table($table)->where('id', $id)->update(['boundary_source' => $source]);
        }
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}
