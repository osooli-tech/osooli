<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        // Enum columns (PostgreSQL native types, VARCHAR on MariaDB)
        PortableSchema::addEnumColumn('parcels', 'asset_type', 'asset_type_enum');
        PortableSchema::addEnumColumn('parcels', 'land_transaction', 'land_transaction_enum');
        PortableSchema::addEnumColumn('parcels', 'allocation_method', 'allocation_method_enum');
        PortableSchema::addEnumColumn('parcels', 'fall_in', 'fall_in_enum');

        // Geometry column — NULL for sub-units (apartments)
        PortableSchema::addGeometryColumn('parcels');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
