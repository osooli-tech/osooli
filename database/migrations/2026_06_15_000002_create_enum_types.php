<?php

declare(strict_types=1);

use App\Support\Database\PortableSchema;
use Illuminate\Database\Migrations\Migration;

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
        // Named types exist only on PostgreSQL; on MariaDB the columns that
        // use them are VARCHARs — see PortableSchema.
        foreach (self::TYPES as $type => $values) {
            PortableSchema::createEnumType($type, $values);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::TYPES)) as $type) {
            PortableSchema::dropEnumType($type);
        }
    }
};
