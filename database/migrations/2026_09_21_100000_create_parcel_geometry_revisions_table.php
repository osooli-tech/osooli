<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every polygon a parcel had before it was redrawn from the dashboard.
 *
 * The audit log records who changed a parcel and when, but not what the
 * geometry was: once a surveyed boundary is overwritten, it is gone. Each row
 * here is the geometry as it stood immediately before one dashboard edit or
 * restore, which is what makes a mistaken redraw reversible.
 *
 * `geom` is nullable because a parcel may have had no polygon at all before
 * its first one was drawn; restoring that row returns it to having none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_geometry_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            // Area of the replaced polygon in square metres, kept so the
            // history can be read without a PostGIS call per row.
            $table->decimal('area_sqm', 16, 2)->nullable();
            // 'edit' (redrawn in the editor) or 'restore' (an older version
            // brought back) — what the change that replaced this polygon was.
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['parcel_id', 'created_at']);
        });

        PortableSchema::addGeometryColumn('parcel_geometry_revisions', index: false);
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_geometry_revisions');
    }
};
