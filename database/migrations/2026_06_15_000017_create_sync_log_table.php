<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_log', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sync_started_at');
            $table->timestamp('sync_finished_at')->nullable();
            $table->integer('records_imported')->default(0);
            $table->integer('records_updated')->default(0);
            $table->string('status', 20); // success / failed / partial
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_log');
    }
};
