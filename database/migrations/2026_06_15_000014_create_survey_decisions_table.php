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
        Schema::create('survey_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->string('qrar_no', 100)->nullable();
            $table->string('report_no', 100)->nullable();
            $table->string('folder')->nullable();
            $table->timestamps();
        });

        PortableSchema::addEnumColumn('survey_decisions', 'qrar_source', 'qrar_source_enum');
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_decisions');
    }
};
