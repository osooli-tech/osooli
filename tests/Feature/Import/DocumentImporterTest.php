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
