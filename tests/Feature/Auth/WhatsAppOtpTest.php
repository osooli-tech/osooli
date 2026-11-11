<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Owner;
use App\Services\Auth\OwnerOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A real sign-in code goes out over WhatsApp (UltraMsg) once the channel is
 * switched on; the listed test phones keep their fixed code and send nothing.
 */
class WhatsAppOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.mobile_otp.channel' => 'whatsapp',
            'auth.mobile_otp.test_code' => null,
            'services.ultramsg.url' => 'https://api.ultramsg.test',
            'services.ultramsg.instance' => 'instance123',
            'services.ultramsg.token' => 'tok',
        ]);
    }

    public function test_the_code_is_sent_to_the_phone_in_international_form(): void
    {
        Http::fake(['api.ultramsg.test/*' => Http::response(['sent' => 'true', 'message' => 'ok', 'id' => 1])]);
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000031', 'phone' => '0536664026']);
        $account = $this->portalAccountFor($owner);

        app(OwnerOtpService::class)->issue($account);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.ultramsg.test/instance123/messages/chat'
                && $request['to'] === '+966536664026'
                && $request['token'] === 'tok'
                && preg_match('/\d{4}/', (string) $request['body']) === 1;
        });
    }

    public function test_a_refusal_is_logged_and_does_not_break_sign_in(): void
    {
        Http::fake(['api.ultramsg.test/*' => Http::response(['error' => 'Wrong token. Please provide token as a GET parameter.'], 401)]);
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000032', 'phone' => '0536664027']);

        $this->assertSame(300, app(OwnerOtpService::class)->issue($this->portalAccountFor($owner)));
    }

    public function test_with_the_channel_off_nothing_is_sent(): void
    {
        Http::fake();
        config(['auth.mobile_otp.channel' => 'log']);
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000033', 'phone' => '0536664028']);

        app(OwnerOtpService::class)->issue($this->portalAccountFor($owner));

        Http::assertNothingSent();
    }
}
