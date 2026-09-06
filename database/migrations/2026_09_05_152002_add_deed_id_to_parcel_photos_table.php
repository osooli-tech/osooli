<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable and not required at the DB level: only Deed-type photos are
     * ever tied to one specific deed record — an Aerial/Ground/BoundarySurvey
     * photo has no deed to point at.
     */
    public function up(): void
    {
        Schema::table('parcel_photos', function (Blueprint $table) {
            $table->foreignId('deed_id')->nullable()->after('parcel_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deed_id');
        });
    }
};
