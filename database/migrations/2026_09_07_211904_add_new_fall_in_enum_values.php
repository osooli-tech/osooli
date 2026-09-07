<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A newer GDB export (دواجن الوطنية) uses these three values for
     * fall_in, none of which the enum had before ('مخطط زراعي' /
     * 'مخطط بلدية' only) — confirmed with the client as genuinely new,
     * approved categories rather than a mismapping of existing ones.
     */
    public function up(): void
    {
        // IF NOT EXISTS keeps this replayable — enum types outlive the table
        // drops that RefreshDatabase performs between test runs.
        DB::statement("ALTER TYPE fall_in_enum ADD VALUE IF NOT EXISTS 'طلبات احكام'");
        DB::statement("ALTER TYPE fall_in_enum ADD VALUE IF NOT EXISTS 'حجة استحكام'");
        DB::statement("ALTER TYPE fall_in_enum ADD VALUE IF NOT EXISTS 'مخطط'");
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
