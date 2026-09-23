<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Saudi National Address (SPL) identifier of a region, city or district.
 *
 * Names are not unique — a region holds several villages called «قرية» — so
 * NationalAddressSeeder recognises a record it loaded before by this id, not
 * by name. NULL for records entered by hand or imported from survey data.
 */
return new class extends Migration
{
    private const TABLES = ['regions', 'cities', 'districts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->unsignedBigInteger('national_address_id')->nullable()->after('name_en');
                $t->unique('national_address_id', "{$table}_national_address_id_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->dropUnique("{$table}_national_address_id_unique");
                $t->dropColumn('national_address_id');
            });
        }
    }
};
