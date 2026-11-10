<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who signs in to the owner portal, apart from who owns land.
 *
 * A portal account is one phone number. Its grants say which owners it opens
 * and how: as the owner themselves ("self") or for them ("delegate"), able to
 * act or only to look. One phone may open a whole family; one owner may be
 * opened by several phones. owners.phone stays the owner's contact number and
 * is kept equal to their own account's phone.
 *
 * The other tables gain portal_account_id: who actually did it, beside the
 * owner it was done for.
 */
return new class extends Migration
{
    /** Tables that record an owner's action and now also the account behind it. */
    private const ACTOR_TABLES = ['personal_access_tokens', 'audit_logs', 'parcel_listings', 'listing_inquiries', 'modification_requests', 'parcel_photos'];

    public function up(): void
    {
        Schema::create('portal_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('phone', 30);
            $table->string('phone_normalized', 30)->unique();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->foreignId('last_owner_id')->nullable()->constrained('owners')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('portal_account_owners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portal_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 10);
            $table->boolean('can_manage')->default(true);
            $table->string('source', 10);
            $table->boolean('needs_review')->default(false);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['portal_account_id', 'owner_id']);
            $table->index('owner_id');
        });

        foreach (self::ACTOR_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('portal_account_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::ACTOR_TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('portal_account_id'));
        }

        Schema::dropIfExists('portal_account_owners');
        Schema::dropIfExists('portal_accounts');
    }
};
