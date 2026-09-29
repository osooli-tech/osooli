<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Parcels\PlacementReview;
use App\Livewire\Reference\BoundaryEditor;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\Region;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\Geo\ParcelPlacement;
use Database\Seeders\NationalAddressBoundariesSeeder;
use Database\Seeders\NationalAddressSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * District, city, region and country boundaries: loading them, checking
 * parcels against them, showing them on the map and drawing them by hand.
 *
 * The fixtures are squares near Buraidah: two districts side by side, a
 * third with no boundary of its own, all inside one city.
 */
class BoundariesTest extends TestCase
{
    use RefreshDatabase;

    private District $east;

    private District $west;

    private District $unbounded;

    public function test_the_seeder_loads_every_level_and_keeps_hand_drawn_ones(): void
    {
        $this->seed(NationalAddressSeeder::class);

        $handDrawn = (int) DB::table('districts')->where('national_address_id', 10100003001)->value('id');
        $this->setBoundary('districts', $handDrawn, $this->square(46.0, 24.0, 0.01), 'manual');

        $this->seed(NationalAddressBoundariesSeeder::class);

        $this->assertSame(1, DB::table('countries')->whereNotNull('geom')->count());
        $this->assertSame(13, DB::table('regions')->whereNotNull('geom')->count());
        $this->assertSame(4581, DB::table('cities')->whereNotNull('geom')->count());
        $this->assertGreaterThan(3700, DB::table('districts')->whereNotNull('geom')->count());

        // The hand-drawn square survived the reload.
        $this->assertSame('manual', DB::table('districts')->where('id', $handDrawn)->value('boundary_source'));
        $this->assertSame(1, $this->containing('districts', 46.005, 24.005, $handDrawn));

        // Central Riyadh lies in exactly one region: Riyadh's.
        $regions = DB::select(
            'SELECT name_en FROM regions WHERE ST_Contains(geom, '.Spatial::point().')',
            [46.6753, 24.7136]
        );
        $this->assertCount(1, $regions);
        $this->assertStringContainsString('Riyadh', (string) $regions[0]->name_en);
    }

    public function test_a_parcel_outside_its_district_is_flagged_with_where_it_actually_is(): void
    {
        $this->fixtures();

        $inside = $this->parcel('1', $this->west, 46.02, 24.02);
        $outside = $this->parcel('2', $this->west, 46.12, 24.02);

        $this->assertSame('ok', ParcelPlacement::forParcel($inside->id)['status']);

        $result = ParcelPlacement::forParcel($outside->id);
        $this->assertSame('outside', $result['status']);
        $this->assertSame('district', $result['level']);
        $this->assertSame('الشرق', $result['actual']['district']);
        $this->assertStringContainsString('الشرق', (string) ParcelPlacement::message($result));
    }

    public function test_a_district_without_a_boundary_is_checked_against_its_city(): void
    {
        $this->fixtures();

        $inCity = $this->parcel('3', $this->unbounded, 46.15, 24.15);
        $outOfCity = $this->parcel('4', $this->unbounded, 47.5, 25.5);

        $this->assertSame('ok', ParcelPlacement::forParcel($inCity->id)['status']);

        $result = ParcelPlacement::forParcel($outOfCity->id);
        $this->assertSame('outside', $result['status']);
        $this->assertSame('city', $result['level']);
    }

    public function test_the_review_page_lists_only_the_misplaced_parcels(): void
    {
        $this->fixtures();
        $this->parcel('IN-1', $this->west, 46.02, 24.02);
        $this->parcel('OUT-1', $this->west, 46.12, 24.02);
        $this->parcel('OUT-CITY', $this->unbounded, 47.5, 25.5);

        $this->actingAs($this->userWith(['parcels.placement']));

        Livewire::test(PlacementReview::class)
            ->assertSee('OUT-1')
            ->assertDontSee('IN-1')
            ->call('show', 'city')
            ->assertSee('OUT-CITY')
            ->assertDontSee('OUT-1');

        $this->get(route('parcels.placement'))->assertOk();
    }

    public function test_the_map_endpoint_returns_the_boundaries_in_view(): void
    {
        $this->fixtures();
        $this->actingAs($this->userWith(['boundaries.view']));

        $this->getJson(route('geo.boundaries', 'districts').'?bbox=45.9,23.9,46.3,24.3&zoom=12')
            ->assertOk()
            ->assertJsonCount(2, 'features')
            ->assertJsonFragment(['name' => 'الشرق', 'source' => 'official']);

        $this->getJson(route('geo.boundaries', 'districts').'?bbox=40,20,41,21&zoom=12')
            ->assertOk()
            ->assertJsonCount(0, 'features');

        $this->getJson(route('geo.boundaries', 'parcels'))->assertNotFound();
    }

    public function test_a_boundary_drawn_by_hand_is_saved_as_manual_and_audited(): void
    {
        $this->fixtures();
        $this->actingAs($this->userWith(['reference.view', 'boundaries.edit']));

        $drawn = json_encode(['type' => 'Polygon', 'coordinates' => [$this->ring(46.2, 24.2, 0.05)]]);

        Livewire::test(BoundaryEditor::class)
            ->call('open', 'districts', $this->unbounded->id)
            ->assertSet('show', true)
            ->call('save', $drawn)
            ->assertHasNoErrors()
            ->assertSet('show', false)
            ->assertDispatched('boundary-saved');

        $this->assertSame('manual', DB::table('districts')->where('id', $this->unbounded->id)->value('boundary_source'));
        $this->assertSame(1, $this->containing('districts', 46.22, 24.22, $this->unbounded->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'district.geometry_edit', 'target_id' => $this->unbounded->id]);

        // A bow-tie is refused with a reason, and nothing changes.
        $bowTie = json_encode(['type' => 'Polygon', 'coordinates' => [[[46, 24], [46.1, 24.1], [46.1, 24], [46, 24.1], [46, 24]]]]);
        Livewire::test(BoundaryEditor::class)
            ->call('open', 'districts', $this->west->id)
            ->call('save', $bowTie)
            ->assertHasErrors('geometry');
        $this->assertSame('official', DB::table('districts')->where('id', $this->west->id)->value('boundary_source'));

        Livewire::test(BoundaryEditor::class)
            ->call('open', 'districts', $this->west->id)
            ->call('remove');
        $this->assertDatabaseHas('districts', ['id' => $this->west->id, 'boundary_source' => null, 'geom' => null]);
    }

    public function test_the_editor_draws_the_nearest_neighbours_first(): void
    {
        $this->fixtures();
        $this->actingAs($this->userWith(['reference.view', 'boundaries.edit']));
        $region = Region::firstOrFail();
        $far = City::create(['region_id' => $region->id, 'name_ar' => 'بعيدة']);
        $near = City::create(['region_id' => $region->id, 'name_ar' => 'قريبة']);
        $this->setBoundary('cities', $far->id, $this->square(50.0, 27.0, 0.3), 'derived');
        $this->setBoundary('cities', $near->id, $this->square(46.4, 24.0, 0.3), 'derived');

        $config = Livewire::test(BoundaryEditor::class)
            ->call('open', 'cities', $this->west->city_id)
            ->viewData('config');

        $names = array_column(array_column(json_decode($config['neighbours'], true)['features'], 'properties'), 'name');
        $this->assertSame(['قريبة', 'بعيدة'], $names);
    }

    public function test_editing_a_boundary_needs_the_reference_edit_permission(): void
    {
        $this->fixtures();
        $this->actingAs($this->userWith(['reference.view']));

        Livewire::test(BoundaryEditor::class)
            ->call('open', 'districts', $this->west->id)
            ->assertForbidden();
    }

    public function test_models_never_read_the_polygon(): void
    {
        $this->fixtures();

        $city = City::with('region.country')->findOrFail($this->west->city_id);

        $this->assertArrayNotHasKey('geom', $city->getAttributes());
        $this->assertArrayNotHasKey('geom', $city->region->getAttributes());
        $this->assertArrayNotHasKey('geom', $city->region->country->getAttributes());
        $this->assertSame('official', District::findOrFail($this->west->id)->getAttribute('boundary_source'));

        // A join keeps the other table's columns.
        $joined = District::query()->join('cities', 'cities.id', '=', 'districts.city_id')
            ->select('districts.*', 'cities.name_ar as city_name')->firstOrFail();
        $this->assertSame('بريدة', $joined->getAttribute('city_name'));
        $this->assertArrayNotHasKey('geom', $joined->getAttributes());
    }

    private function fixtures(): void
    {
        $country = Country::create(['name_ar' => 'السعودية', 'iso_code' => 'SA']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'القصيم']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'بريدة']);
        $this->west = District::create(['city_id' => $city->id, 'name_ar' => 'الغرب']);
        $this->east = District::create(['city_id' => $city->id, 'name_ar' => 'الشرق']);
        $this->unbounded = District::create(['city_id' => $city->id, 'name_ar' => 'بلا حدود']);

        $this->setBoundary('regions', $region->id, $this->square(45.0, 23.0, 2.0), 'official');
        $this->setBoundary('cities', $city->id, $this->square(46.0, 24.0, 0.3), 'derived');
        $this->setBoundary('districts', $this->west->id, $this->square(46.0, 24.0, 0.1), 'official');
        $this->setBoundary('districts', $this->east->id, $this->square(46.1, 24.0, 0.1), 'official');
    }

    /** A parcel with a small square polygon centred on the point given. */
    private function parcel(string $number, District $district, float $lng, float $lat): Parcel
    {
        $plan = Plan::firstOrCreate(['plan_no' => 'P-'.$district->id, 'district_id' => $district->id]);
        $parcel = Parcel::create(['parcel_no' => $number, 'geo_id' => 'geo-'.$number, 'plan_id' => $plan->id]);
        $this->setBoundary('parcels', $parcel->id, $this->square($lng - 0.0002, $lat - 0.0002, 0.0004), null);

        return $parcel;
    }

    private function setBoundary(string $table, int $id, string $geojson, ?string $source): void
    {
        DB::update("UPDATE {$table} SET geom = ".Spatial::fromGeoJson().' WHERE id = ?', [$geojson, $id]);

        if ($table !== 'parcels') {
            DB::table($table)->where('id', $id)->update(['boundary_source' => $source]);
        }
    }

    /** How many rows of the table with this id contain the point: 0 or 1. */
    private function containing(string $table, float $lng, float $lat, int $id): int
    {
        return count(DB::select(
            "SELECT id FROM {$table} WHERE id = ? AND ST_Contains(geom, ".Spatial::point().')',
            [$id, $lng, $lat]
        ));
    }

    private function square(float $west, float $south, float $size): string
    {
        return (string) json_encode(['type' => 'MultiPolygon', 'coordinates' => [[$this->ring($west, $south, $size)]]]);
    }

    /** @return list<array{0: float, 1: float}> */
    private function ring(float $west, float $south, float $size): array
    {
        return [[$west, $south], [$west + $size, $south], [$west + $size, $south + $size], [$west, $south + $size], [$west, $south]];
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
