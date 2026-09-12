<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DisplayLayerGeoJsonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
    }

    private function makeProject(string $name): Project
    {
        $project = Project::create(['name' => $name, 'code' => 'MF-1', 'area' => 100.0, 'length' => 40.0]);

        DB::update(
            "UPDATE projects SET geom = ST_SetSRID(ST_Multi(ST_GeomFromText('POLYGON((46.5 24.5, 46.501 24.5, 46.501 24.501, 46.5 24.501, 46.5 24.5))')), 4326) WHERE id = ?",
            [$project->id]
        );

        return $project;
    }

    private function makeBuilding(string $name): Building
    {
        $building = Building::create(['name' => $name, 'code' => 'B-1', 'area' => 50.0, 'length' => 20.0]);

        DB::update(
            "UPDATE buildings SET geom = ST_SetSRID(ST_Multi(ST_GeomFromText('POLYGON((46.6 24.6, 46.601 24.6, 46.601 24.601, 46.6 24.601, 46.6 24.6))')), 4326) WHERE id = ?",
            [$building->id]
        );

        return $building;
    }

    public function test_projects_endpoint_returns_geojson_features(): void
    {
        $this->makeProject('مزرعة صغيرة رقم 1');

        $response = $this->actingAs($this->user)->getJson(route('geo.projects'));

        $response->assertOk();
        $response->assertJsonPath('type', 'FeatureCollection');
        $response->assertJsonCount(1, 'features');
        $response->assertJsonPath('features.0.properties.name', 'مزرعة صغيرة رقم 1');
    }

    public function test_buildings_endpoint_returns_geojson_features(): void
    {
        $this->makeBuilding('بئر ماء');

        $response = $this->actingAs($this->user)->getJson(route('geo.buildings'));

        $response->assertOk();
        $response->assertJsonPath('type', 'FeatureCollection');
        $response->assertJsonCount(1, 'features');
        $response->assertJsonPath('features.0.properties.name', 'بئر ماء');
    }

    public function test_rows_without_geometry_are_excluded(): void
    {
        Project::create(['name' => 'بلا هندسة', 'code' => null, 'area' => null, 'length' => null]);

        $response = $this->actingAs($this->user)->getJson(route('geo.projects'));

        $response->assertOk();
        $response->assertJsonCount(0, 'features');
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson(route('geo.projects'))->assertUnauthorized();
        $this->getJson(route('geo.buildings'))->assertUnauthorized();
    }
}
