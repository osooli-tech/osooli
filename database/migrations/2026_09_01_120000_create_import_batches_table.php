<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // kind and status are plain strings cast to PHP enums, not native
            // Postgres enums. This table's native-enum neighbours (deed_status,
            // asset_type, …) mirror fixed ArcGIS coded domains — source data.
            // Import lifecycle is application state that changes with the code,
            // and a native enum would need a migration to alter.
            $table->string('kind', 20);
            $table->string('status', 20)->index();

            $table->string('original_filename');
            $table->bigInteger('byte_size');
            $table->integer('received_chunks')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('stored_path', 500)->nullable();

            $table->jsonb('preview')->nullable();
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
