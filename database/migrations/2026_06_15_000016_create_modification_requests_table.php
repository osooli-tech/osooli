<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('owners')->restrictOnDelete();
            $table->string('field_name', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('requested_by');
        });

        DB::statement(
            "ALTER TABLE modification_requests ADD COLUMN status modification_request_status_enum NOT NULL DEFAULT 'pending'"
        );

        DB::statement('CREATE INDEX idx_modification_requests_status ON modification_requests(status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('modification_requests');
    }
};
