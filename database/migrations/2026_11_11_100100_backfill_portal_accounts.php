<?php

declare(strict_types=1);

use App\Support\Portal\LegacyAccountBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives every owner phone its portal account, keeping who can sign in exactly
 * as it was: a phone shared by several owners still opens only the one it
 * opened before, and the rest wait for staff review.
 *
 * `php artisan portal:accounts-audit` shows the result before it is written.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LegacyAccountBackfill::class)->run();
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')->update(['portal_account_id' => null]);
        DB::table('portal_account_owners')->delete();
        DB::table('portal_accounts')->delete();
    }
};
