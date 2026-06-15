<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deed_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->restrictOnDelete();
            // NULL when ownership share is unknown (written as text inside deed document)
            $table->decimal('ownership_share', 5, 2)->nullable();
            // OBJECTID of the individual owner record in ArcGIS (each co-owner is a separate Feature)
            $table->bigInteger('source_gdb_id')->nullable();
            $table->timestamps();

            $table->unique(['deed_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deed_owners');
    }
};
