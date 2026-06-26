<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('national_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->timestamps();
        });

        // Partial unique index: national_id must be unique when not null
        DB::statement(
            'CREATE UNIQUE INDEX uq_owners_national_id ON owners(national_id) WHERE national_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
