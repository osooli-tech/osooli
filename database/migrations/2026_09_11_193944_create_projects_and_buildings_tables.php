<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A display/identification layer only — the client's own description —
     * not tied to `parcels`: a project zone or building footprint routinely
     * overlaps more than one parcel, so a single parcel_id would misrepresent
     * it. Shown on the map as its own layer; nothing here drives ownership,
     * deeds, or any decision the parcels/deeds tables already own.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->double('area')->nullable();
            $table->double('length')->nullable();
            $table->timestamps();
        });
        PortableSchema::addGeometryColumn('projects');

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->double('area')->nullable();
            $table->double('length')->nullable();
            $table->timestamps();
        });
        PortableSchema::addGeometryColumn('buildings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('projects');
    }
};
