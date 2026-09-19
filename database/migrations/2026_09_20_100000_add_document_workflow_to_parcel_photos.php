<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_TYPE = 'document_status_enum';

    /** @var list<string> */
    private const STATUSES = ['معلق', 'معتمد', 'مرفوض'];

    private const APPROVED = 'معتمد';

    private const STATUS_INDEX = 'parcel_photos_status_index';

    public function up(): void
    {
        $this->createStatusType();

        Schema::table('parcel_photos', function (Blueprint $table): void {
            // What the uploader called the file. The stored name is generated,
            // so this is the only place the human-readable name survives.
            $table->string('original_name', 255)->nullable()->after('photo_url');
            $table->string('mime_type', 100)->nullable()->after('original_name');
            $table->bigInteger('size_bytes')->nullable()->after('mime_type');

            // Which disk `photo_url` is relative to. NULL means the row predates
            // the private-disk upload path — see ParcelPhoto::storageLocation().
            $table->string('storage_disk', 20)->nullable()->after('size_bytes');

            // nullOnDelete, not cascade: removing a staff account must not take
            // the deed scans they handled with it.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        /*
         * The enum column is added with raw SQL because the Blueprint has no
         * type for a named Postgres enum — the same reason `photo_type` was
         * added this way in create_parcel_photos_table.
         *
         * The default is 'معتمد', not 'معلق', and that is deliberate on two
         * counts. Existing rows are already in use and must not vanish behind
         * a review queue the moment this runs — ADD COLUMN ... DEFAULT stamps
         * them all in one pass. And the rows the artisan link:* commands write
         * describe files an administrator placed on the server themselves,
         * which is the approval. Only the upload form, where a file arrives
         * from a browser, sets 'معلق' explicitly.
         */
        DB::statement(
            'ALTER TABLE parcel_photos ADD COLUMN status '.self::STATUS_TYPE
            ." NOT NULL DEFAULT '".self::APPROVED."'"
        );

        // The review queue reads by status on every page load.
        DB::statement('CREATE INDEX IF NOT EXISTS '.self::STATUS_INDEX.' ON parcel_photos (status)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::STATUS_INDEX);

        Schema::table('parcel_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'original_name',
                'mime_type',
                'size_bytes',
                'storage_disk',
                'reviewed_at',
                'rejection_reason',
                'status',
            ]);
        });

        // Only safe once the column above is gone; nothing else uses the type.
        DB::statement('DROP TYPE IF EXISTS '.self::STATUS_TYPE);
    }

    /**
     * Postgres has no CREATE TYPE IF NOT EXISTS, and enum types survive the
     * table drops RefreshDatabase performs — so swallow duplicates, exactly as
     * create_enum_types.php does.
     */
    private function createStatusType(): void
    {
        $literals = implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            self::STATUSES
        ));

        DB::statement('DO $$ BEGIN
            CREATE TYPE '.self::STATUS_TYPE." AS ENUM ({$literals});
        EXCEPTION WHEN duplicate_object THEN NULL; END $$;");
    }
};
