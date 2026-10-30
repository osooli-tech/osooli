<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A buyer asking Sukooki to put them in touch with the seller of a listed
 * parcel. Neither side sees the other's number; staff arrange the contact.
 * One request per buyer and listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parcel_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['parcel_listing_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_inquiries');
    }
};
