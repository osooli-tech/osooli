<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\ParcelGeoJsonImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class ParcelGeoJsonImporterTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/fixtures/import/parcels.geojson');
    }

    private function importer(): ParcelGeoJsonImporter
    {
        return app(ParcelGeoJsonImporter::class);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'parcels' => DB::table('parcels')->count(),
            'deeds' => DB::table('deeds')->count(),
            'owners' => DB::table('owners')->count(),
            'deed_owners' => DB::table('deed_owners')->count(),
            'boundaries' => DB::table('parcel_boundaries')->count(),
        ];
    }

    public function test_analyze_writes_nothing(): void
    {
        $before = $this->counts();

        $preview = $this->importer()->analyze($this->fixture());

        $this->assertSame($before, $this->counts(), 'analyze() must never write');
        $this->assertSame(5, $preview->willCreate, 'five distinct Geo_IDs in the fixture');
        $this->assertSame(0, $preview->willUpdate);
    }

    public function test_analyze_reports_updates_once_the_data_exists(): void
    {
        $this->importer()->commit($this->fixture());

        $preview = $this->importer()->analyze($this->fixture());

        $this->assertSame(0, $preview->willCreate);
        $this->assertSame(5, $preview->willUpdate);
    }

    /**
     * The dashboard upload flow's complete() endpoint only sniffs the first
     * few KB of a .geojson upload for "looks like JSON" (see
     * ImportUploadController::looksLikeGeoJson()) precisely so that the real
     * "does this actually have features" check happens here instead, where a
     * failure is a recoverable job outcome rather than an OOM risk in an HTTP
     * request. Valid JSON with no features key at all must land here.
     */
    public function test_analyze_throws_when_there_is_no_features_key(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'no-features-geojson');
        file_put_contents($path, json_encode(['type' => 'FeatureCollection']));

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No features found in the GeoJSON file.');

            $this->importer()->analyze($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_commit_creates_parcels_deeds_and_owners(): void
    {
        $result = $this->importer()->commit($this->fixture());

        $this->assertSame(5, $result->created);
        $this->assertSame(0, $result->errors);
        $this->assertDatabaseHas('parcels', ['geo_id' => '91-25', 'parcel_no' => '91']);
        $this->assertDatabaseHas('deeds', ['deed_no' => '311608002898']);
    }

    /**
     * created/updated must count DISTINCT PARCELS, the same unit the preview
     * reports, so the wizard's confirm screen reconciles. Counting upsert
     * conflicts instead would report 5 created + 2 updated on the first run —
     * the two extra being the second deed-groups of 28-112 and 34-82 touching
     * rows this same run had just inserted.
     */
    public function test_created_and_updated_count_distinct_parcels(): void
    {
        $first = $this->importer()->commit($this->fixture());

        $this->assertSame(5, $first->created);
        $this->assertSame(0, $first->updated, 'a parcel inserted this run is not also an update');

        $second = $this->importer()->commit($this->fixture());

        $this->assertSame(0, $second->created);
        $this->assertSame(5, $second->updated);
    }

    public function test_preview_and_commit_agree_on_the_same_numbers(): void
    {
        $preview = $this->importer()->analyze($this->fixture());
        $result = $this->importer()->commit($this->fixture());

        $this->assertSame($preview->willCreate, $result->created);
        $this->assertSame($preview->willUpdate, $result->updated);
    }

    /** @return list<array<string, mixed>> */
    private function fixtureFeatures(): array
    {
        /** @var array{features: list<array<string, mixed>>} $raw */
        $raw = json_decode((string) file_get_contents($this->fixture()), true);

        return $raw['features'];
    }

    /**
     * parcels.geo_id is NOT NULL UNIQUE, so '' is a legal value — a blank one
     * would insert a real parcel that every later blank-Geo_ID feature then
     * overwrites through ON CONFLICT (geo_id).
     */
    public function test_it_skips_a_feature_with_a_blank_geo_id(): void
    {
        $features = $this->fixtureFeatures();
        $good = $features[0];
        $blank = $features[0];
        $blank['properties']['Geo_ID'] = '';

        $result = $this->importer()->importFeatures([$good, $blank]);

        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->errors, 'a blank Geo_ID is a skip, not an error');
        $this->assertDatabaseHas('parcels', ['geo_id' => $good['properties']['Geo_ID']]);
        $this->assertDatabaseMissing('parcels', ['geo_id' => '']);
    }

    /** An absent key must skip too, not raise an undefined-key ErrorException. */
    public function test_it_skips_a_feature_whose_geo_id_key_is_missing(): void
    {
        $features = $this->fixtureFeatures();
        $good = $features[0];
        $missing = $features[0];
        unset($missing['properties']['Geo_ID']);

        $result = $this->importer()->importFeatures([$good, $missing]);

        $this->assertSame(1, $result->skipped);
        $this->assertSame(0, $result->errors);
        $this->assertSame(1, DB::table('parcels')->count());
    }

    public function test_commit_is_idempotent(): void
    {
        $this->importer()->commit($this->fixture());
        $first = $this->counts();

        $this->importer()->commit($this->fixture());

        $this->assertSame($first, $this->counts(), 'a second commit must not duplicate anything');
    }

    public function test_one_parcel_can_carry_two_deeds(): void
    {
        $this->importer()->commit($this->fixture());

        $parcelId = DB::table('parcels')->where('geo_id', '28-112')->value('id');

        $this->assertSame(2, DB::table('deeds')->where('parcel_id', $parcelId)->count());
    }

    public function test_one_deed_number_can_sit_on_two_parcels(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame(2, DB::table('deeds')->where('deed_no', '911605004832')->count());
    }

    public function test_co_owners_on_one_deed_produce_two_links(): void
    {
        $this->importer()->commit($this->fixture());

        $deedId = DB::table('deeds')->where('deed_no', '311608002898')->value('id');

        $this->assertSame(2, DB::table('deed_owners')->where('deed_id', $deedId)->count());
    }

    public function test_an_owner_is_reused_across_parcels_by_national_id(): void
    {
        $this->importer()->commit($this->fixture());
        $this->importer()->commit($this->fixture());

        $this->assertSame(1, DB::table('owners')->where('national_id', '9999999999')->count());
    }

    public function test_it_stores_the_survey_area_as_measured_area(): void
    {
        $this->importer()->commit($this->fixture());

        $parcelId = DB::table('parcels')->where('geo_id', '91-25')->value('id');
        $measured = DB::table('parcel_boundaries')->where('parcel_id', $parcelId)->value('measured_area');

        $this->assertNotNull($measured, 'Survey_Area must land in measured_area');
        $this->assertEqualsWithDelta(10150.48, (float) $measured, 0.01);
    }

    public function test_it_keeps_hijri_dates_as_plain_text(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame(
            '1442-04-21',
            DB::table('deeds')->where('deed_no', '311608002898')->value('deed_date_hijri')
        );
    }

    public function test_it_maps_numeric_domain_codes_to_enum_values(): void
    {
        $this->importer()->commit($this->fixture());

        $this->assertSame('محدث', DB::table('deeds')->where('deed_no', '311608002898')->value('deed_status'));
    }

    public function test_it_stores_geometry_as_multipolygon_in_4326(): void
    {
        $this->importer()->commit($this->fixture());

        $row = DB::selectOne(
            "SELECT ST_GeometryType(geom) AS type, ST_SRID(geom) AS srid FROM parcels WHERE geo_id = '91-25'"
        );

        $this->assertSame('ST_MultiPolygon', $row->type);
        $this->assertSame(4326, (int) $row->srid);
    }
}
