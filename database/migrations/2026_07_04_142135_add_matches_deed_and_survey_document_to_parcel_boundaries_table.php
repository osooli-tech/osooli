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
        Schema::table('parcel_boundaries', function (Blueprint $table) {
            // Whether the physical boundary survey matches the deed ("مطابق / غير مطابق")
            $table->boolean('matches_deed')->nullable()->after('measured_area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_boundaries', function (Blueprint $table) {
            $table->dropColumn('matches_deed');
        });
    }
};
