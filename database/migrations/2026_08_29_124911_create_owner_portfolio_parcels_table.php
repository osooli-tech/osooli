<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which portfolio a parcel sits in, for a given owner.
 *
 * owner_id is stored alongside parcel_id (not reached only through the
 * portfolio) so a parcel can be assigned to at most one portfolio per owner:
 * moving it is an update of this row, never a second insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_portfolio_parcels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['owner_id', 'parcel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_portfolio_parcels');
    }
};
