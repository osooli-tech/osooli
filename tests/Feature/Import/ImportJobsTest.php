<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Jobs\AnalyzeImportBatch;
use App\Jobs\CommitImportBatch;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

final class ImportJobsTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/jobs_'.uniqid();
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function documentsBatch(ImportStatus $status): ImportBatch
    {
        $user = User::create([
            'name' => 'مدير', 'email' => 'jobs'.uniqid().'@test.local',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $planId = DB::table('plans')->insertGetId(['plan_no' => '25', 'created_at' => now(), 'updated_at' => now()]);
        $parcelId = DB::table('parcels')->insertGetId([
            'geo_id' => '91-25', 'parcel_no' => '91', 'plan_id' => $planId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('deeds')->insert([
            'parcel_id' => $parcelId, 'deed_no' => '311608002898',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zipPath = $this->tmp.'/docs.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('311608002898.pdf', '%PDF-1.4 fake');
        $zip->close();

        return ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'kind' => ImportKind::Documents,
            'status' => $status,
            'original_filename' => 'docs.zip',
            'byte_size' => filesize($zipPath),
            'stored_path' => $zipPath,
        ]);
    }

    public function test_analyze_stores_a_preview_and_writes_nothing(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);

        (new AnalyzeImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Previewed, $batch->status);
        $this->assertSame(1, $batch->preview['total_items']);
        $this->assertNotNull($batch->analyzed_at);
        $this->assertSame(0, DB::table('parcel_photos')->count());
    }

    public function test_commit_writes_and_completes(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Previewed);

        (new CommitImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Completed, $batch->status);
        $this->assertSame(1, $batch->result['created']);
        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_commit_refuses_a_batch_that_was_never_previewed(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);

        (new CommitImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Uploaded, $batch->status);
        $this->assertSame(0, DB::table('parcel_photos')->count());
    }

    public function test_commit_runs_only_once_for_a_batch(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Previewed);

        (new CommitImportBatch($batch->id))->handle();
        (new CommitImportBatch($batch->id))->handle();

        $this->assertSame(1, DB::table('parcel_photos')->count());
    }

    public function test_a_failure_records_the_message(): void
    {
        $batch = $this->documentsBatch(ImportStatus::Uploaded);
        $batch->forceFill(['stored_path' => $this->tmp.'/missing.zip'])->save();

        (new AnalyzeImportBatch($batch->id))->handle();

        $batch->refresh();
        $this->assertSame(ImportStatus::Failed, $batch->status);
        $this->assertNotEmpty($batch->error_message);
    }
}
