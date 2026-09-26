<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The choices made on a geodatabase import's review screen — which layer is
 * which, where each district belongs, which boundary set counts — kept with
 * the batch so the commit applies exactly what was decided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->json('options')->nullable()->after('preview');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->dropColumn('options');
        });
    }
};
