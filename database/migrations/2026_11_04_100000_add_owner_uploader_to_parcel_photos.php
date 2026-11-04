<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owners may now add photos of their own parcels from the portal or the app.
 * uploaded_by keeps naming the member of staff; this names the owner, so a
 * reviewer sees who sent a photo and the owner can follow their own uploads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_photos', function (Blueprint $table): void {
            $table->foreignId('uploaded_by_owner')->nullable()->after('uploaded_by')->constrained('owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parcel_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uploaded_by_owner');
        });
    }
};
