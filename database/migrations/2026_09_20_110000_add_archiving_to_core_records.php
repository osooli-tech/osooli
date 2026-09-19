<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving for the three records whose loss is unrecoverable.
 *
 * Deleting a parcel today cascades through its deeds, boundaries, survey
 * decisions, photos, modification requests, portfolio links and sub-units,
 * with no way back short of a database restore. Archiving keeps the row and
 * its whole subtree intact while removing it from view.
 *
 * Deliberately limited to parcels, deeds and owners. Secondary records —
 * photos, portfolio links — keep hard deletes: the places that would have to
 * learn about archiving grow with every table covered, and "restore a deleted
 * photo" is not a request anyone makes, unlike "restore that parcel".
 *
 * `archived_by` answers the question an audit entry alone does not: who is
 * responsible for this row being out of circulation right now.
 */
return new class extends Migration
{
    private const TABLES = ['parcels', 'deeds', 'owners'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->softDeletes();
                $t->foreignId('archived_by')->nullable()->after('deleted_at')
                    ->constrained('users')->nullOnDelete();
            });

            // Every listing, report and scope filters on "not archived", and on
            // a table of any size that predicate is the first thing touched.
            // Partial index: only live rows are indexed, so it stays small.
            DB::statement(
                "CREATE INDEX idx_{$table}_live ON {$table} (id) WHERE deleted_at IS NULL"
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP INDEX IF EXISTS idx_{$table}_live");

            Schema::table($table, function (Blueprint $t): void {
                $t->dropConstrainedForeignId('archived_by');
                $t->dropSoftDeletes();
            });
        }
    }
};
