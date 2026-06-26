<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_boundaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('n_border')->nullable();
            $table->string('s_border')->nullable();
            $table->string('e_border')->nullable();
            $table->string('w_border')->nullable();
            $table->decimal('n_dim', 10, 2)->nullable();
            $table->decimal('s_dim', 10, 2)->nullable();
            $table->decimal('e_dim', 10, 2)->nullable();
            $table->decimal('w_dim', 10, 2)->nullable();
            // Area measured on-the-ground from N/S/E/W dimensions — distinct from deed_area and ST_Area
            $table->decimal('measured_area', 14, 2)->nullable();
            // Survey date awaiting from ArcGIS — nullable until client adds it
            $table->string('survey_date', 10)->nullable();
            $table->foreignId('engineering_office_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_boundaries');
    }
};
