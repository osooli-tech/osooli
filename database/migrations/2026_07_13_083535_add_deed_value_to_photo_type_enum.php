<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A no-op on MariaDB, where the column is a VARCHAR.
        PortableSchema::addEnumValue('photo_type_enum', 'صك');
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
