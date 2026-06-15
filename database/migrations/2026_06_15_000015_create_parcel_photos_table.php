<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('photo_url', 500);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE parcel_photos ADD COLUMN photo_type photo_type_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_photos');
    }
};
