<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Parcels\PlacementReview;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\Geo\PlacementFix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Reassigning a misplaced parcel's district from «قطع خارج حيّها».
 *
 * The fixture is the case met in the client's data: the first importer
 * filed every district under الدرعية, so «العمارية» — no boundary of its own
 * — sits in الدرعية, while its parcels lie inside the National Address city
 * العمارية, next door.
 */
class PlacementFixTest extends TestCase
{
    use RefreshDatabase;

    private City $diriyah;

    private City $ammariyah;

    private District $district;

    public function test_a_district_filed_under_the_wrong_city_is_moved_to_the_city_its_parcels_lie_in(): void
    {
        $this->fixtures();
        $first = $this->parcel('91', '25', 46.32, 24.82);
        $this->parcel('92', '25', 46.33, 24.83);
        $this->parcel('10', '26', 46.34, 24.84);

        $options = PlacementFix::options($first->id);
        $this->assertCount(1, $options);
        $this->assertSame('district', $options[0]['kind']);
        $this->assertSame($this->ammariyah->id, $options[0]['target_id']);
        $this->assertSame(['parcels' => 3, 'located' => 3, 'inside' => 3], array_intersect_key($options[0], array_flip(['parcels', 'located', 'inside'])));

        $this->actingAs($this->userWith(['parcels.placement', 'parcels.placement_fix']));

        Livewire::test(PlacementReview::class)
            ->call('show', 'city')
            ->assertSee('إعادة تعيين')
            ->assertSee('91')
            ->call('openFix', $first->id)
            ->assertSee('نقل حي «العمارية» إلى مدينة «العمارية»')
            ->call('applyFix', 'district', $this->ammariyah->id)
            ->assertDispatched('toast', type: 'success')
            ->assertSet('fixing', null)
            ->assertDontSee('إعادة تعيين');

        $this->assertSame($this->ammariyah->id, (int) DB::table('districts')->where('id', $this->district->id)->value('city_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'district.reassign', 'target_id' => $this->district->id]);
    }

    public function test_a_plan_lying_in_another_bounded_district_is_moved_there(): void
    {
        $this->fixtures();
        $named = District::create(['city_id' => $this->ammariyah->id, 'name_ar' => 'الشمال']);
        $this->boundary('districts', $named->id, 46.30, 24.80, 0.1, 'official');
        $parcel = $this->parcel('91', '25', 46.32, 24.82);

        $options = collect(PlacementFix::options($parcel->id))->keyBy('kind');
        $this->assertSame(['plan', 'district'], $options->keys()->all());
        $this->assertSame($named->id, $options['plan']['target_id']);

        $this->actingAs($this->userWith(['parcels.placement', 'parcels.placement_fix']));
        Livewire::test(PlacementReview::class)
            ->call('openFix', $parcel->id)
            ->call('applyFix', 'plan', $named->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame($named->id, (int) Plan::query()->where('plan_no', '25')->value('district_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.reassign']);
    }

    public function test_a_stale_or_forged_move_changes_nothing(): void
    {
        $this->fixtures();
        $parcel = $this->parcel('91', '25', 46.32, 24.82);
        $this->actingAs($this->userWith(['parcels.placement', 'parcels.placement_fix']));

        Livewire::test(PlacementReview::class)
            ->call('openFix', $parcel->id)
            ->call('applyFix', 'district', $this->diriyah->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertSame($this->diriyah->id, (int) DB::table('districts')->where('id', $this->district->id)->value('city_id'));
    }

    public function test_reassigning_needs_its_own_permission(): void
    {
        $this->fixtures();
        $parcel = $this->parcel('91', '25', 46.32, 24.82);
        $this->actingAs($this->userWith(['parcels.placement']));

        Livewire::test(PlacementReview::class)
            ->call('show', 'city')
            ->assertSee('91')
            ->assertDontSee('إعادة تعيين')
            ->call('openFix', $parcel->id)
            ->assertForbidden();
    }

    private function fixtures(): void
    {
        app()->setLocale('ar');
        $country = Country::create(['name_ar' => 'السعودية', 'iso_code' => 'SA']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'منطقة الرياض']);
        $this->diriyah = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $this->ammariyah = City::create(['region_id' => $region->id, 'name_ar' => 'العمارية']);
        $this->district = District::create(['city_id' => $this->diriyah->id, 'name_ar' => 'العمارية']);

        $this->boundary('regions', $region->id, 45.0, 24.0, 2.0, 'official');
        $this->boundary('cities', $this->diriyah->id, 46.50, 24.70, 0.1, 'official');
        $this->boundary('cities', $this->ammariyah->id, 46.30, 24.80, 0.1, 'official');
    }

    private function parcel(string $number, string $planNo, float $lng, float $lat): Parcel
    {
        $plan = Plan::firstOrCreate(['plan_no' => $planNo], ['district_id' => $this->district->id]);
        $parcel = Parcel::create(['parcel_no' => $number, 'geo_id' => $number.'-'.$planNo, 'plan_id' => $plan->id]);
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
