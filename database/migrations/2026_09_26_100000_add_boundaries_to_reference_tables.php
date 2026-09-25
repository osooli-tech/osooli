<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boundaries for the country, its regions, cities and districts, so a
 * parcel can be checked against the place its plan says it is in.
 *
 * `boundary_source` says how far to trust the polygon:
 *   official    — the National Address (SPL) boundary, as published
 *   derived     — built from official boundaries (a city from its districts)
 *   approximate — no official boundary exists; the land nearer this place
 *                 than any other in its region
 *   manual      — drawn on the dashboard; never replaced by a data reload
 */
return new class extends Migration
{
    private const TABLES = ['countries', 'regions', 'cities', 'districts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            PortableSchema::addGeometryColumn($table, 'geom');

            Schema::table($table, function (Blueprint $t): void {
                $t->string('boundary_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (\App\Support\Database\Dialect::isPostgres()) {
                PortableSchema::dropIndex($table, "idx_{$table}_geom");
            }

            Schema::table($table, function (Blueprint $t): void {
                $t->dropColumn(['geom', 'boundary_source']);
            });
        }
    }
};
