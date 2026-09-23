<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('deed_no', 100)->nullable()->index();
            // Hijri date stored as plain text 'YYYY-MM-DD' — no calendar conversion
            $table->string('deed_date_hijri', 10)->nullable();
            $table->decimal('deed_area', 14, 2)->nullable();
            $table->timestamps();
        });

        PortableSchema::addEnumColumn('deeds', 'deed_status', 'deed_status_enum');
        PortableSchema::addEnumColumn('deeds', 'deed_class', 'deed_class_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('deeds');
    }
};
