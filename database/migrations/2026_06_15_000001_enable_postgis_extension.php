<?php

declare(strict_types=1);

use App\Support\Database\Dialect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB's spatial types are built in; only PostgreSQL needs PostGIS.
        if (Dialect::isPostgres()) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        }
    }

    public function down(): void
    {
        // Intentionally not dropping postgis — other data may depend on it
    }
};
