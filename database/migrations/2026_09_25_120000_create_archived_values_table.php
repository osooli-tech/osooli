<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single field values taken out of a record that itself stays in use.
 *
 * Archiving covers whole parcels, deeds and owners. A data cleanup also
 * empties or corrects single fields — a placeholder phone, a malformed
 * national id, a mistyped date — and those old values would otherwise be
 * lost. Each row keeps one such value, with the reason it was removed, so it
 * can be put back from the archive screen.
 *
 * `record_table` is the table the value came from, not a model name, because
 * the cleanup also touches rows with no archivable model (boundaries,
 * districts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_values', function (Blueprint $table) {
            $table->id();
            $table->string('record_table', 50);
            $table->unsignedBigInteger('record_id');
            $table->string('field', 50);
            $table->text('value')->nullable();
            $table->string('reason');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_table', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_values');
    }
};
