<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A parcel may name a "parent owner": the owner it stays under although its
 * deed is in someone else's name — land a father handed to his children, a
 * holding that sits under its founder. The parent owner goes on seeing it in
 * the portal; a parcel with none simply left their hands.
 *
 * portal_settings holds what a parent owner may see of such a parcel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->foreignId('parent_owner_id')->nullable()->constrained('owners')->nullOnDelete();
        });

        Schema::create('portal_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('linked_parcels')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_settings');

        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_owner_id');
        });
    }
};
