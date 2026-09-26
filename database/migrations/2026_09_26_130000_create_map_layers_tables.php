<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom map layers: any layer a geodatabase brings that is not parcels,
 * projects or buildings — wells, roads, fences, whatever the client adds —
 * kept with every attribute it had, to be switched on over the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_layers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            // Point, LineString, Polygon (or their Multi forms), or Mixed.
            $table->string('geometry_type', 30)->nullable();
            // The attribute names and types the layer's features carry.
            $table->json('fields')->nullable();
            $table->string('color', 7)->default('#8e44ad');
            $table->boolean('visible_by_default')->default(false);
            $table->unsignedInteger('feature_count')->default(0);
            // The file the layer last came from.
            $table->string('source')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('map_layer_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('map_layer_id')->constrained()->cascadeOnDelete();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
        // Plain geometry, not MultiPolygon: a custom layer may be points or lines.
        PortableSchema::addGeometryColumn('map_layer_features', 'geom', true, 'Geometry');
    }

    public function down(): void
    {
        Schema::dropIfExists('map_layer_features');
        Schema::dropIfExists('map_layers');
    }
};
