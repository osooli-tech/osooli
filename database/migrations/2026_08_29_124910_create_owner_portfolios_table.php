<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named grouping an owner defines over their own holdings — e.g. an owner
 * with parcels across several projects splitting them into separate
 * portfolios. Distinct from the city-based portfolios on the dashboard, which
 * are derived rather than owner-defined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_portfolios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['owner_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_portfolios');
    }
};
