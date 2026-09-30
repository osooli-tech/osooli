<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The owner portal authenticates against a separate `owners` table, so a
     * document download made through it cannot be attributed via `user_id`
     * (that foreign key points at `users`). A parallel nullable column keeps
     * the same audit trail for owner-portal actions without mixing the two
     * identities into one column.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
