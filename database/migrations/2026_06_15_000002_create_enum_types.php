<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE deed_status_enum AS ENUM ('محدث', 'قديم')");

        DB::statement("CREATE TYPE deed_class_enum AS ENUM ('زراعي', 'سكني', 'صناعي')");

        DB::statement("CREATE TYPE asset_type_enum AS ENUM ('أرض', 'شقة', 'عمارة', 'فيلا', 'مستودع')");

        DB::statement("CREATE TYPE qrar_source_enum AS ENUM ('بلدي', 'مكتب هندسي', 'بدون')");

        DB::statement("CREATE TYPE fall_in_enum AS ENUM ('مخطط زراعي', 'مخطط بلدية')");

        DB::statement("CREATE TYPE allocation_method_enum AS ENUM ('محدد بدقة', 'محدد حسب الموقع العام', 'لم يتم تحديد الموقع')");

        DB::statement("CREATE TYPE land_transaction_enum AS ENUM ('مباعة', 'مؤجرة', 'قيد البيع', 'خاصة')");

        DB::statement("CREATE TYPE photo_type_enum AS ENUM ('جوية', 'أرضية')");

        DB::statement("CREATE TYPE modification_request_status_enum AS ENUM ('pending', 'sent_to_arcgis', 'applied', 'rejected')");
    }

    public function down(): void
    {
        DB::statement('DROP TYPE IF EXISTS modification_request_status_enum');
        DB::statement('DROP TYPE IF EXISTS photo_type_enum');
        DB::statement('DROP TYPE IF EXISTS land_transaction_enum');
        DB::statement('DROP TYPE IF EXISTS allocation_method_enum');
        DB::statement('DROP TYPE IF EXISTS fall_in_enum');
        DB::statement('DROP TYPE IF EXISTS qrar_source_enum');
        DB::statement('DROP TYPE IF EXISTS asset_type_enum');
        DB::statement('DROP TYPE IF EXISTS deed_class_enum');
        DB::statement('DROP TYPE IF EXISTS deed_status_enum');
    }
};
