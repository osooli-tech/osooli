<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Owner;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Until SMS delivery is live, production accepts the fixed test code only for
 * the listed test numbers — never for a real owner's phone.
 */
class OwnerOtpTestCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.mobile_otp.test_code' => '6666',
            'auth.mobile_otp.test_phones' => ['+966500000000'],
        ]);
    }

    public function test_outside_production_every_owner_gets_the_test_code(): void
    {
        $owner = $this->owner('0551234567');

        app(OwnerOtpService::class)->issue($owner);

        $this->assertSame('6666', Cache::get("owner_otp_{$owner->id}"));
    }

    public function test_in_production_a_listed_test_number_gets_the_test_code(): void
    {
        $this->app['env'] = 'production';
        // Listed as +966…, stored as 05… — both sides are compared in one form.
        $owner = $this->owner('0500000000');

        app(OwnerOtpService::class)->issue($owner);

        $this->assertSame('6666', Cache::get("owner_otp_{$owner->id}"));
    }

    public function test_in_production_any_other_number_gets_a_random_code(): void
    {
        $this->app['env'] = 'production';
        // Five digits, so a random four-digit code can never match it by chance.
        config(['auth.mobile_otp.test_code' => '66666']);
        $owner = $this->owner('0551234567');

        app(OwnerOtpService::class)->issue($owner);

        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) Cache::get("owner_otp_{$owner->id}"));
        $this->assertFalse(app(OwnerOtpService::class)->verify($owner, '66666'));
    }

    private function owner(string $phone): Owner
    {
        return Owner::create(['name' => 'مالك تجريبي', 'national_id' => '10000000'.random_int(10, 99), 'phone' => $phone]);
    }
}
