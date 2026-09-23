<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;

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
        // A no-op on MariaDB, where the column is a VARCHAR.
        PortableSchema::addEnumValue('fall_in_enum', 'طلبات احكام');
        PortableSchema::addEnumValue('fall_in_enum', 'حجة استحكام');
        PortableSchema::addEnumValue('fall_in_enum', 'مخطط');
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
