<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Owner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The owner portal's own sign-in: phone + OTP into the `owner` guard, kept
 * entirely separate from the internal team's `web` guard.
 */
class PortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = Owner::create([
            'name' => 'مالك تجريبي',
            'national_id' => '1000000001',
            'phone' => '0500000001',
        ]);
    }

    public function test_a_guest_visiting_the_portal_is_sent_to_the_portals_own_login(): void
    {
        $this->get('/portal')->assertRedirect(route('portal.login'));
    }

    public function test_the_full_sign_in_flow_logs_the_owner_into_the_owner_guard(): void
    {
        $this->post(route('portal.login.submit'), ['phone' => '0500000001'])
            ->assertRedirect(route('portal.otp'));

        $this->post(route('portal.otp.verify'), ['otp' => '6666'])
            ->assertRedirect(route('portal.dashboard'));

        $this->assertTrue(Auth::guard('owner')->check());
        $this->assertSame($this->owner->id, Auth::guard('owner')->id());
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_an_unregistered_phone_is_rejected(): void
    {
        $this->post(route('portal.login.submit'), ['phone' => '0599999999'])
            ->assertSessionHasErrors('phone');

        $this->assertFalse(session()->has('portal_otp_owner_id'));
    }

    public function test_a_wrong_code_is_rejected_and_does_not_sign_in(): void
    {
        $this->post(route('portal.login.submit'), ['phone' => '0500000001']);

        $this->post(route('portal.otp.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_the_otp_screen_is_unreachable_without_a_pending_login(): void
    {
        $this->get(route('portal.otp'))->assertRedirect(route('portal.login'));
    }

    public function test_logging_out_clears_the_owner_session(): void
    {
        $this->post(route('portal.login.submit'), ['phone' => '0500000001']);
        $this->post(route('portal.otp.verify'), ['otp' => '6666']);

        $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));

        $this->assertFalse(Auth::guard('owner')->check());
    }

    public function test_a_signed_in_owner_can_reach_the_dashboard(): void
    {
        $this->actingAs($this->owner, 'owner')
            ->get(route('portal.dashboard'))
            ->assertOk();
    }
}
