<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\User;
use App\Support\Database\Spatial;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Reverses an applied import from its undo log.
 *
 * A row is only put back if nobody has touched it since the import: its
 * `updated_at` must still be the import's own stamp. Anything edited by
 * hand afterwards is left as it is and listed in the result, so undoing an
 * import never throws away someone's later work.
 *
 * Rows the import created are deleted children first; an owner, plan or
 * district it created stays if something else has come to use it since.
 */
final class DeedImportUndo
{
    /** @return array<string, mixed> the updated run */
    public function run(string $id, ?User $user): array
    {
        @set_time_limit(0);
        // Large files: the owner index and per-file bookkeeping grow with them.
        @ini_set('memory_limit', '512M');

        $run = ImportRuns::find($id) ?? throw new RuntimeException('Import not found.');
        $undo = ImportRuns::readJson(ImportRuns::path($id, 'undo.json')) ?? throw new RuntimeException('Nothing to undo.');

        $run['state'] = 'undoing';
        ImportRuns::save($run);

        $stamp = (string) $undo['stamp'];
        $kept = [];
        $restored = 0;
        $deleted = 0;

        try {
            DB::transaction(function () use ($undo, $stamp, &$kept, &$restored, &$deleted): void {
                foreach (array_reverse($undo['updated']) as $change) {
                    $current = DB::table($change['table'])->where('id', $change['id'])->value('updated_at');

                    if ($current === null || ! self::sameStamp((string) $current, $stamp)) {
                        $kept[] = $change['table'].'#'.$change['id'];

                        continue;
                    }

                    DB::table($change['table'])->where('id', $change['id'])->update($change['old']);
                    $restored++;
                }

                foreach ($undo['geometry'] as $parcelId => $geojson) {
                    if (in_array('parcels#'.$parcelId, $kept, true)) {
                        continue;
                    }

                    $geojson === null
                        ? DB::update('UPDATE parcels SET geom = NULL WHERE id = ?', [$parcelId])
                        : DB::update('UPDATE parcels SET geom = '.Spatial::fromGeoJson().' WHERE id = ?', [Spatial::multiPolygonJson($geojson), $parcelId]);
                }

                foreach (DeedImportApplier::CREATED_ORDER as $table) {
                    foreach (array_chunk($undo['created'][$table] ?? [], 500) as $ids) {
                        foreach (DB::table($table)->whereIn('id', $ids)->get(['id', 'updated_at']) as $row) {
                            if (! self::sameStamp((string) $row->updated_at, $stamp) || $this->inUse($table, (int) $row->id)) {
                                $kept[] = $table.'#'.$row->id;

                                continue;
                            }

                            DB::table($table)->where('id', $row->id)->delete();
                            $deleted++;
                        }
                    }
                }
            });

            $run['state'] = 'undone';
            $run['undo_result'] = ['restored' => $restored, 'deleted' => $deleted, 'kept' => $kept];
        } catch (Throwable $e) {
            report($e);
            $run['state'] = 'applied';
            $run['error'] = strtok($e->getMessage(), "\n") ?: $e::class;
        }

        $run['undone_at'] = now()->toIso8601String();
        $run['undone_by'] = $user?->name;
        ImportRuns::save($run);

        return $run;
    }

    /** Whether a created row has since gained something that depends on it. */
    private function inUse(string $table, int $id): bool
    {
        return match ($table) {
            'owners' => DB::table('deed_owners')->where('owner_id', $id)->exists(),
            'plans' => DB::table('parcels')->where('plan_id', $id)->exists(),
            'districts' => DB::table('plans')->where('district_id', $id)->exists(),
            'deeds' => DB::table('parcel_photos')->where('deed_id', $id)->exists(),
            'parcels' => DB::table('deeds')->where('parcel_id', $id)->exists()
                || DB::table('parcel_photos')->where('parcel_id', $id)->exists(),
            default => false,
        };
    }

    /** Timestamps compared to the second, however the driver formats them. */
    private static function sameStamp(string $a, string $b): bool
    {
        return substr(str_replace('T', ' ', $a), 0, 19) === substr($b, 0, 19);
    }
}
