<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enum types and their values, in the order the source data encodes them
     * (see docs/gdb-coded-domains.md).
     *
     * @var array<string, list<string>>
     */
    private const TYPES = [
        'deed_status_enum' => ['محدث', 'قديم'],
        'deed_class_enum' => ['زراعي', 'سكني', 'صناعي'],
        'asset_type_enum' => ['أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع'],
        'qrar_source_enum' => ['بلدي', 'مكتب هندسي', 'بدون'],
        'fall_in_enum' => ['مخطط زراعي', 'مخطط بلدية'],
        'allocation_method_enum' => ['محدد بدقة', 'محدد حسب الموقع العام', 'لم يتم تحديد الموقع'],
        'land_transaction_enum' => ['مباعة', 'مؤجرة', 'قيد البيع', 'خاصة'],
        'photo_type_enum' => ['جوية', 'أرضية'],
        'modification_request_status_enum' => ['pending', 'sent_to_arcgis', 'applied', 'rejected'],
    ];

    public function up(): void
    {
        foreach (self::TYPES as $type => $values) {
            $literals = implode(', ', array_map(
                static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
                $values
            ));

            // Postgres has no CREATE TYPE IF NOT EXISTS, and enum types survive
            // the table drops RefreshDatabase performs — so swallow duplicates.
            DB::statement("DO $$ BEGIN
                CREATE TYPE {$type} AS ENUM ({$literals});
            EXCEPTION WHEN duplicate_object THEN NULL; END $$;");
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::TYPES)) as $type) {
            DB::statement("DROP TYPE IF EXISTS {$type}");
        }
    }
};
