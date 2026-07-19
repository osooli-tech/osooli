<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            // Estimated price per square meter (from the survey data source).
            $table->decimal('m_price', 12, 2)->nullable()->after('source_gdb_id');
            // Estimated total parcel value.
            $table->decimal('parcel_price', 16, 2)->nullable()->after('m_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn(['m_price', 'parcel_price']);
        });
    }
};
