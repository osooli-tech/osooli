<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal texts become bilingual, following the same name_ar / name_en shape the
 * geographic tables already use.
 *
 * The existing single-language columns held Arabic, so they are renamed rather
 * than replaced — no content is lost. English starts null and the reader falls
 * back to Arabic until it is filled in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->renameColumn('title', 'title_ar');
            $table->renameColumn('content', 'content_ar');
        });

        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->string('title_en', 120)->nullable()->after('title_ar');
            $table->longText('content_en')->nullable()->after('content_ar');
        });
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->dropColumn(['title_en', 'content_en']);
        });

        Schema::table('legal_documents', function (Blueprint $table): void {
            $table->renameColumn('title_ar', 'title');
            $table->renameColumn('content_ar', 'content');
        });
    }
};
