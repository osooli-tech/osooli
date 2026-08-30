<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These two columns already exist on production, added there directly in a
 * prior session before this migration was written. Guarded with hasColumn
 * checks so it is a no-op there — running it as an unconditional add fails
 * with "column already exists" the moment it reaches that database — while
 * still adding the columns on a fresh install, where they are genuinely
 * missing and the default User factory (which sets email_verified_at) needs
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'remember_token']);
        });
    }
};
