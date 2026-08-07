<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The OTP login looked owners up with an unindexed double-regexp whereRaw,
 * which scanned the whole table per attempt (~57s on shared hosting).
 * Store the normalised phone once, indexed, and query it directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table): void {
            $table->string('phone_normalized', 30)->nullable()->index();
        });

        // Backfill mirroring Owner::normalisePhone(): digits only, drop a
        // 00966/966 country prefix, trim leading zeros.
        DB::statement(<<<'SQL'
            UPDATE owners
            SET phone_normalized = NULLIF(
                ltrim(
                    regexp_replace(
                        regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'),
                        '^(00966|966)', ''
                    ),
                    '0'
                ),
                ''
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table): void {
            $table->dropColumn('phone_normalized');
        });
    }
};
