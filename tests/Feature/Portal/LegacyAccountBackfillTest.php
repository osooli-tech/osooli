<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Services\Auth\OwnerOtpService;
use App\Support\Portal\LegacyAccountBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Turning today's owner phones into portal accounts must not let anyone in
 * who could not get in before.
 */
class LegacyAccountBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_unique_phone_gets_one_usable_self_grant(): void
    {
        $owner = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927', 'phone' => '0500000001']);

        app(LegacyAccountBackfill::class)->run();

        $account = PortalAccount::findByPhone('0500000001');
        $this->assertSame([$owner->id], $account?->usableOwnerIds());
        $this->assertTrue($account->grants()->first()->isSelf());
        $this->assertSame('نجلاء', $account->name);
    }

    public function test_a_shared_phone_opens_only_the_owner_that_opens_it_today(): void
    {
        $family = collect(['1041721927', '1041721885', '1041721950'])
            ->map(fn (string $id, int $i) => Owner::create(['name' => 'فرد '.$i, 'national_id' => $id, 'phone' => '536664026']));
        $today = app(OwnerOtpService::class)->findOwnerByPhone('536664026');

        $result = app(LegacyAccountBackfill::class)->run();

        $account = PortalAccount::findByPhone('536664026');
        $this->assertSame([$today->id], $account?->usableOwnerIds());
        $this->assertSame(2, $account->grants()->where('needs_review', true)->count());
        $this->assertSame(2, $result['pending']);
        $this->assertNull($account->name, 'a shared phone is named by staff, not after one of its owners');
        $this->assertCount(3, $family);
    }

    public function test_the_plan_names_the_chosen_owner_before_anything_is_written(): void
    {
        Owner::create(['name' => 'أ', 'national_id' => '1000000001', 'phone' => '0536664026']);
        Owner::create(['name' => 'ب', 'national_id' => '1000000002', 'phone' => '536664026']);
        $today = app(OwnerOtpService::class)->findOwnerByPhone('536664026');

        $plan = app(LegacyAccountBackfill::class)->plan();

        $this->assertCount(1, $plan);
        $this->assertSame($today->id, $plan[0]['chosen_owner_id']);
        $this->assertCount(1, $plan[0]['pending_owner_ids']);
        $this->assertSame(0, PortalAccount::count());
    }

    public function test_archived_owners_get_no_account(): void
    {
        Owner::create(['name' => 'مؤرشف', 'national_id' => '1000000001', 'phone' => '0500000001'])->archive();

        app(LegacyAccountBackfill::class)->run();

        $this->assertSame(0, PortalAccount::count());
    }

    public function test_tokens_are_linked_or_dropped(): void
    {
        $withPhone = Owner::create(['name' => 'له جوال', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $withoutPhone = Owner::create(['name' => 'بلا جوال', 'national_id' => '1000000002']);
        $kept = $withPhone->createToken('mobile')->accessToken;
        $withoutPhone->createToken('mobile');

        $result = app(LegacyAccountBackfill::class)->run();

        $this->assertSame(PortalAccount::findByPhone('0500000001')?->id, $kept->fresh()?->portal_account_id);
        $this->assertSame(0, $withoutPhone->tokens()->count());
        $this->assertSame(1, $result['tokens_linked']);
        $this->assertSame(1, $result['tokens_dropped']);
    }

    public function test_running_twice_changes_nothing(): void
    {
        Owner::create(['name' => 'أ', 'national_id' => '1000000001', 'phone' => '0500000001']);
        Owner::create(['name' => 'ب', 'national_id' => '1000000002', 'phone' => '0500000001']);

        app(LegacyAccountBackfill::class)->run();
        $before = [PortalAccount::count(), PortalAccountOwner::count(), PortalAccountOwner::where('needs_review', true)->count()];
        app(LegacyAccountBackfill::class)->run();

        $this->assertSame($before, [PortalAccount::count(), PortalAccountOwner::count(), PortalAccountOwner::where('needs_review', true)->count()]);
        $this->assertSame(0, (int) DB::table('personal_access_tokens')->count());
    }

    public function test_the_audit_command_prints_the_plan_without_writing(): void
    {
        Owner::create(['name' => 'أ', 'national_id' => '1000000001', 'phone' => '0500000001']);
        Owner::create(['name' => 'ب', 'national_id' => '1000000002', 'phone' => '0500000001']);

        $this->artisan('portal:accounts-audit')
            ->expectsOutputToContain('500000001')
            ->assertSuccessful();

        $this->assertSame(0, PortalAccount::count());
    }
}
