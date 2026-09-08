<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Real incident: an account saved as "Geomatics.sa@gmail.com" (capital G)
 * could not sign in by typing "geomatics.sa@gmail.com" — Postgres string
 * equality is case-sensitive, and nothing normalised either side. Every
 * assertion here is either "case is normalised on write" or "lookup
 * tolerates whatever case was typed," since either alone leaves the other
 * half of the bug in place.
 */
class LoginEmailCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_is_stored_lowercase_regardless_of_input_case(): void
    {
        $user = User::create([
            'name' => 'مستخدم', 'email' => 'Geomatics.SA@Gmail.com',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->assertSame('geomatics.sa@gmail.com', $user->email);
    }

    public function test_the_email_is_trimmed_on_write(): void
    {
        $user = User::create([
            'name' => 'مستخدم', 'email' => '  spaced@example.com  ',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->assertSame('spaced@example.com', $user->email);
    }

    public function test_a_user_can_log_in_typing_a_different_case_than_stored(): void
    {
        Mail::shouldReceive('raw')->once();

        User::create([
            'name' => 'مستخدم', 'email' => 'Geomatics.sa@gmail.com',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'GEOMATICS.SA@GMAIL.COM',
            'password' => 'password',
        ])->assertRedirect(route('otp.show'));

        $this->assertGuest();
    }

    public function test_login_still_fails_for_a_genuinely_wrong_password(): void
    {
        Mail::shouldReceive('raw')->never();

        User::create([
            'name' => 'مستخدم', 'email' => 'user@sakuki.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);

        $this->post('/login', ['email' => 'user@sakuki.test', 'password' => 'wrong'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_the_scope_command_finds_a_user_by_a_different_case(): void
    {
        $user = User::create([
            'name' => 'مستخدم', 'email' => 'geomatics.sa@gmail.com',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '123']);

        $exitCode = Artisan::call('app:scope-user-to-owner', [
            'email' => 'Geomatics.SA@Gmail.com',
            'national_id' => '123',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($user->fresh()->scopedOwners()->where('owners.id', $owner->id)->exists());
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
