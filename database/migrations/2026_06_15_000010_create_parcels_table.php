<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_no', 50)->nullable();
            $table->string('geo_id', 100)->unique();
            $table->foreignId('plan_id')->nullable()->constrained()->restrictOnDelete();
            // parent_parcel_id added below after table creation (self-reference)
            $table->bigInteger('source_gdb_id')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // Self-referencing FK — must be added after table exists
        Schema::table('parcels', function (Blueprint $table) {
            $table->foreignId('parent_parcel_id')->nullable()->after('plan_id')
                ->constrained('parcels')->cascadeOnDelete();
        });

        // Enum columns using PostgreSQL native types
        DB::statement('ALTER TABLE parcels ADD COLUMN asset_type asset_type_enum');
        DB::statement('ALTER TABLE parcels ADD COLUMN land_transaction land_transaction_enum');
        DB::statement('ALTER TABLE parcels ADD COLUMN allocation_method allocation_method_enum');
        DB::statement('ALTER TABLE parcels ADD COLUMN fall_in fall_in_enum');

        // PostGIS geometry column — NULL for sub-units (apartments)
        DB::statement('ALTER TABLE parcels ADD COLUMN geom geometry(MultiPolygon, 4326)');
        DB::statement('CREATE INDEX idx_parcels_geom ON parcels USING GIST(geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
