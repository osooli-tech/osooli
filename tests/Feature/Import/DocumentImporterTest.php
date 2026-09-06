<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\PhotoType;
use App\Services\Import\DocumentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class DocumentImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->tmp = sys_get_temp_dir().'/di_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    /** @param list<string> $names */
    private function zipOf(array $names, string $filename = 'docs.zip'): string
    {
        $path = $this->tmp.'/'.$filename;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($names as $name) {
            $zip->addFromString($name, '%PDF-1.4 fake');
        }
        $zip->close();

        return $path;
    }

    /** Creates a parcel on a plan, plus an optional deed, and returns the parcel id. */
    private function makeParcel(string $geoId, string $parcelNo, string $planNo, ?string $deedNo = null): int
    {
        $planId = DB::table('plans')->insertGetId([
            'plan_no' => $planNo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $parcelId = DB::table('parcels')->insertGetId([
            'geo_id' => $geoId, 'parcel_no' => $parcelNo, 'plan_id' => $planId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($deedNo !== null) {
            DB::table('deeds')->insert([
                'parcel_id' => $parcelId, 'deed_no' => $deedNo,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $parcelId;
    }

    private function importer(): DocumentImporter
    {
        return app(DocumentImporter::class);
    }

    public function test_analyze_writes_nothing(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $zip = $this->zipOf(['311608002898.pdf']);

        $preview = $this->importer()->analyze($zip);

        $this->assertSame(0, DB::table('parcel_photos')->count(), 'analyze() must never write');
        $this->assertSame(1, $preview->totalItems);
        $this->assertSame('deed', $preview->details['rule']);
    }

    public function test_it_detects_the_deed_rule_and_links_by_deed_number(): void
    {
        $parcelId = $this->makeParcel('91-25', '91', '25', '311608002898');

        $result = $this->importer()->commit($this->zipOf(['311608002898.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'photo_type' => PhotoType::Deed->value,
        ]);
    }

    public function test_it_detects_the_survey_rule_and_links_by_parcel_and_plan(): void
    {
        $parcelId = $this->makeParcel('29-623', '29', '623');

        $result = $this->importer()->commit($this->zipOf(['29 - 623.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'photo_type' => PhotoType::BoundarySurvey->value,
        ]);
    }

    public function test_it_handles_non_numeric_plan_numbers(): void
    {
        $parcelId = $this->makeParcel('7-20A', '7', '20A');

        $this->importer()->commit($this->zipOf(['7 - 20A.pdf']));

        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $parcelId]);
    }

    /**
     * Regression test for the actual data-loss bug this branch reintroduced:
     * master's 79f6621 added deed_id to parcel_photos specifically because
     * keying only on (parcel_id, photo_type) meant a second deed's scan on
     * the same parcel silently overwrote the first's row. This uses the
     * client's real geo_id (28-112 carries two deeds in the production
     * geodatabase) so the scenario is unambiguous, not a synthetic edge case.
     */
    public function test_a_parcel_with_two_deeds_gets_two_parcel_photos_rows_not_one_overwriting_the_other(): void
    {
        $parcelId = $this->makeParcel('28-112', '28', '112', '100000000001');
        DB::table('deeds')->insert([
            'parcel_id' => $parcelId, 'deed_no' => '100000000002',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->importer()->commit($this->zipOf(['100000000001.pdf', '100000000002.pdf']));

        $this->assertSame(2, $result->created, 'each deed scan is a genuine create, not an overwrite of the other');
        $this->assertSame(0, $result->updated);
        $this->assertSame(
            2,
            DB::table('parcel_photos')->where('parcel_id', $parcelId)->count(),
            'the parcel must end up with two distinct parcel_photos rows, one per deed — not one row clobbered by the second import'
        );

        $deedIds = DB::table('deeds')->where('parcel_id', $parcelId)->pluck('id', 'deed_no');

        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'deed_id' => $deedIds['100000000001'],
            'photo_type' => PhotoType::Deed->value,
        ]);
        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'deed_id' => $deedIds['100000000002'],
            'photo_type' => PhotoType::Deed->value,
        ]);
    }

    public function test_a_survey_map_links_with_a_null_deed_id(): void
    {
        $parcelId = $this->makeParcel('29-623', '29', '623');

        $this->importer()->commit($this->zipOf(['29 - 623.pdf']));

        $this->assertDatabaseHas('parcel_photos', [
            'parcel_id' => $parcelId,
            'photo_type' => PhotoType::BoundarySurvey->value,
            'deed_id' => null,
        ]);
    }

    /**
     * The NULL-key hazard: WHERE deed_id = NULL never matches in raw SQL, so
     * if commit() naively passed deed_id => null through to a plain equality
     * search, every re-import of a survey map would find nothing, decide the
     * row does not exist, and insert a duplicate. Eloquent's query builder
     * actually converts a null search value into whereNull() automatically,
     * so this must stay idempotent.
     */
    public function test_reimporting_the_same_survey_map_archive_does_not_duplicate_the_row(): void
    {
        $parcelId = $this->makeParcel('29-623', '29', '623');
        $zip = $this->zipOf(['29 - 623.pdf']);

        $first = $this->importer()->commit($zip);
        $second = $this->importer()->commit($zip);

        $this->assertSame(1, $first->created);
        $this->assertSame(0, $first->updated);
        $this->assertSame(0, $second->created, 'the null deed_id key must still be recognised as the same row, not a fresh insert');
        $this->assertSame(1, $second->updated);
        $this->assertSame(1, DB::table('parcel_photos')->where('parcel_id', $parcelId)->count());
    }

    /**
     * Regression test for the parked analyze()/commit() discrepancy (point 4
     * of the fix): before deed_id was part of the key, two deed scans for the
     * same parcel both keyed on (parcel_id, photo_type) alone, so analyze()
     * predicted 2 creates while commit() actually produced 1 create + 1
     * update (the second write "updated" the row the first write had just
     * created). With deed_id in the key both files key differently and both
     * predictions must agree that both are genuine creates. Uses the
     * client's other real two-deed parcel (34-82) for concreteness.
     */
    public function test_analyzing_two_deed_scans_for_the_same_parcel_predicts_two_creates_not_a_create_and_an_update(): void
    {
        $parcelId = $this->makeParcel('34-82', '34', '82', '200000000001');
        DB::table('deeds')->insert([
            'parcel_id' => $parcelId, 'deed_no' => '200000000002',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zip = $this->zipOf(['200000000001.pdf', '200000000002.pdf']);

        $preview = $this->importer()->analyze($zip);
        $this->assertSame(2, $preview->willCreate, 'two distinct deeds on one parcel are two genuine creates, not a create/update collision');
        $this->assertSame(0, $preview->willUpdate);

        $result = $this->importer()->commit($zip);
        $this->assertSame(2, $result->created, 'the preview must match what commit() actually does');
        $this->assertSame(0, $result->updated);
    }

    public function test_one_deed_number_on_two_parcels_links_both(): void
    {
        $first = $this->makeParcel('401-61', '401', '61', '911605004832');
        $second = $this->makeParcel('401-2', '401', '2', '911605004832');

        $result = $this->importer()->commit($this->zipOf(['911605004832.pdf']));

        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $first]);
        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $second]);
        $this->assertSame(2, $result->created, 'a deed on two parcels must link to both');
    }

    public function test_the_majority_rule_wins_and_the_minority_is_reported_unmatched(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $this->makeParcel('29-623', '29', '623');

        $preview = $this->importer()->analyze($this->zipOf([
            '311608002898.pdf', '396426002606.pdf', '396426002607.pdf', '29 - 623.pdf',
        ]));

        $this->assertSame('deed', $preview->details['rule']);
        $this->assertSame(3, $preview->unmatched, 'two unknown deeds plus the survey map that does not fit the chosen rule');
    }

    public function test_unmatched_files_are_never_written(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $result = $this->importer()->commit($this->zipOf(['311608002898.pdf', '999999999999.pdf']));

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_it_names_the_unmatched_files_in_the_preview(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $preview = $this->importer()->analyze($this->zipOf(['311608002898.pdf', '999999999999.pdf']));

        $this->assertContains('999999999999.pdf', $preview->details['unmatched_files']);
    }

    public function test_committing_twice_does_not_duplicate_documents(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $zip = $this->zipOf(['311608002898.pdf']);

        $first = $this->importer()->commit($zip);
        $second = $this->importer()->commit($zip);

        $this->assertSame(1, DB::table('parcel_photos')->count());
        $this->assertSame(1, $first->created, 'the first pass creates the row');
        $this->assertSame(0, $first->updated);
        $this->assertSame(0, $second->created, 'the second pass must not be counted as a creation');
        $this->assertSame(1, $second->updated, 'the second pass updates the existing row instead');
    }

    public function test_it_ignores_entries_that_are_not_pdfs(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');

        $preview = $this->importer()->analyze($this->zipOf(['311608002898.pdf', 'readme.txt']));

        $this->assertSame(1, $preview->totalItems, 'non-PDF entries are not counted as items');
    }

    public function test_commit_with_no_matching_rule_reports_the_same_details_shape_as_a_normal_commit(): void
    {
        // "abc" matches neither rule: not all digits (Deed) and no hyphen (SurveyMap).
        $result = $this->importer()->commit($this->zipOf(['abc.pdf']));

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->skipped);
        $this->assertArrayHasKey('photo_type', $result->details, 'the null-rule path must expose the same keys as a normal commit');
        $this->assertArrayHasKey('unmatched_files', $result->details);
        $this->assertSame(['abc.pdf'], $result->details['unmatched_files']);
    }

    /**
     * Regression test for I1 in the final review: analyze() used to hardcode
     * willUpdate to 0 no matter what, so a second analyze of an
     * already-imported archive still previewed every link as a create even
     * though commit() would report them all as updates.
     */
    public function test_a_second_analyze_of_an_already_committed_archive_previews_updates_not_creates(): void
    {
        $this->makeParcel('91-25', '91', '25', '311608002898');
        $zip = $this->zipOf(['311608002898.pdf']);

        $this->importer()->commit($zip);
        $preview = $this->importer()->analyze($zip);

        $this->assertSame(0, $preview->willCreate);
        $this->assertSame(1, $preview->willUpdate);
    }

    public function test_a_second_commit_never_sees_the_first_archives_extracted_files(): void
    {
        $first = $this->makeParcel('91-25', '91', '25', '311608002898');
        $second = $this->makeParcel('401-2', '401', '2', '502134007711');

        // Both archives land in the same temp directory (same dirname($sourcePath))
        // but under different filenames — exactly what LinkDeedDocuments does when
        // it randomises only the zip filename. A fixed, shared extraction root
        // would let the first archive's file leak into the second inspect() call.
        $this->importer()->commit($this->zipOf(['311608002898.pdf'], 'first.zip'));

        // Simulate the operator deleting the first document between runs.
        DB::table('parcel_photos')->where('parcel_id', $first)->delete();

        // The second archive does not contain 311608002898.pdf at all.
        $this->importer()->commit($this->zipOf(['502134007711.pdf'], 'second.zip'));

        $this->assertDatabaseMissing('parcel_photos', ['parcel_id' => $first], 'a stale extracted copy must not resurrect the deleted document');
        $this->assertDatabaseHas('parcel_photos', ['parcel_id' => $second]);
    }
}
