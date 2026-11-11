<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account may be a company acting as agent for owners — it owns nothing
 * itself but sees and runs their land — so it carries its company name and
 * commercial registration. And what an agent may do for each owner is set by
 * staff grant by grant: null permissions mean everything, so nothing changes
 * for an account until someone narrows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table): void {
            $table->string('kind', 10)->default('person')->after('name');
            $table->string('cr_number', 20)->nullable()->after('kind');
        });

        Schema::table('portal_account_owners', function (Blueprint $table): void {
            $table->json('permissions')->nullable()->after('can_manage');
        });
    }

    public function down(): void
    {
        Schema::table('portal_account_owners', fn (Blueprint $table) => $table->dropColumn('permissions'));
        Schema::table('portal_accounts', fn (Blueprint $table) => $table->dropColumn(['kind', 'cr_number']));
    }
};
