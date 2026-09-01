<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Import\ImportResult;
use App\Services\Import\ParcelGeoJsonImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportParcelsGeoJson extends Command
{
    protected $signature = 'app:import-parcels-geojson
                            {--file=import/sakoki_with_deed.geojson : GeoJSON path relative to storage/app}';

    protected $description = 'Import parcels, deeds, owners, boundaries and survey decisions from a GeoJSON export';

    public function handle(ParcelGeoJsonImporter $importer): int
    {
        $path = storage_path('app/'.$this->option('file'));
        $startedAt = now();

        try {
            $result = $importer->commit($path);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn('✗ '.$warning);
        }

        $this->logSync($startedAt, $result, basename($path));

        $this->table(
            ['قطع جديدة', 'قطع محدّثة', 'صكوك', 'ملاك جدد', 'حدود', 'قرارات', 'أخطاء'],
            [[
                $result->created,
                $result->updated,
                $result->details['deeds'] ?? 0,
                $result->details['owners'] ?? 0,
                $result->details['boundaries'] ?? 0,
                $result->details['decisions'] ?? 0,
                $result->errors,
            ]]
        );

        return $result->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function logSync(Carbon $startedAt, ImportResult $result, string $source): void
    {
        DB::statement(
            'INSERT INTO sync_log (sync_started_at, sync_finished_at, records_imported, records_updated, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $startedAt,
                now(),
                $result->created,
                $result->updated,
                $result->errors === 0 ? 'success' : 'partial',
                sprintf(
                    'src=%s | new=%d upd=%d deeds=%d owners=%d err=%d',
                    $source, $result->created, $result->updated,
                    $result->details['deeds'] ?? 0, $result->details['owners'] ?? 0, $result->errors
                ),
            ]
        );
    }
}
