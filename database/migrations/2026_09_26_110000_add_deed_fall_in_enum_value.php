<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ArcGIS parcel table marks parcels held by a title deed as
     * fall_in 'صك' — a category of its own, not one of the plan types nor
     * 'حجة استحكام'.
     */
    public function up(): void
    {
        // A no-op on MariaDB, where the column is a VARCHAR.
        PortableSchema::addEnumValue('fall_in_enum', 'صك');
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
