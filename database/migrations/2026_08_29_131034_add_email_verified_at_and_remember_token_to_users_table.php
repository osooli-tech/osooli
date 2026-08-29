<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These two columns already exist on production — this migration only brings
 * the repository's schema in line with it, so a fresh local database matches
 * what is actually running and the default User factory (which sets
 * email_verified_at) can insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'remember_token']);
        });
    }
};
