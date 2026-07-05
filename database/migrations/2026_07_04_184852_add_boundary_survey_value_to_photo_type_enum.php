<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TYPE photo_type_enum ADD VALUE 'كروكي مساحي'");
    }

    /**
     * Reverse the migrations.
     *
     * Postgres does not support removing a value from an enum type without
     * recreating it — left as a no-op, matching this project's other
     * enum-value migrations.
     */
    public function down(): void {}
};
