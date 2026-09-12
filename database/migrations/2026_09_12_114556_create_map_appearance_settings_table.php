<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single settings row (id 1) so the client can override the map's
     * default colours from the dashboard instead of a developer hardcoding
     * them. Only overridden keys are stored; anything absent falls back to
     * MapAppearanceSetting::DEFAULTS.
     */
    public function up(): void
    {
        Schema::create('map_appearance_settings', function (Blueprint $table) {
            $table->id();
            $table->json('overrides')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_appearance_settings');
    }
};
