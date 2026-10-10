<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class LoginLockoutPerSourceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const STRANGER = '203.0.113.50';

    private const OWNER = '198.51.100.7';

    private function attempt(string $login, string $password, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('login'), ['login' => $login, 'password' => $password]);
    }

    private function adminWithPassword(): User
    {
        return $this->createUser('Locked Out Admin', 'lock-admin@example.com', User::ROLE_ADMIN, 'Hoshishi', ['username' => 'lock.admin']);
    }

    public function test_a_stranger_guessing_from_another_address_cannot_lock_the_real_user_out(): void
    {
        $admin = $this->adminWithPassword();

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS + 2; $i++) {
            $this->attempt('lock.admin', 'wrong-guess', self::STRANGER);
        }

        // The stranger is now locked...
        $this->attempt('lock.admin', 'password123', self::STRANGER)->assertSessionHasErrors('login');
        $this->assertGuest();

        // ...but the real admin, from their own address, signs in normally.
        $this->attempt('lock.admin', 'password123', self::OWNER)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_same_address_is_still_locked_after_too_many_wrong_passwords(): void
    {
        $this->adminWithPassword();

        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
            $this->attempt('lock.admin', 'wrong-guess', self::OWNER);
        }

        $this->attempt('lock.admin', 'password123', self::OWNER)->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_the_unlock_command_clears_the_lock_from_every_address(): void
    {
        $this->adminWithPassword();

        foreach ([self::STRANGER, self::OWNER] as $ip) {
            for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
                $this->attempt('lock.admin', 'wrong-guess', $ip);
            }
            $this->assertTrue(RateLimiter::tooManyAttempts(LoginThrottle::accountKey('lock.admin', $ip), LoginThrottle::MAX_ATTEMPTS));
        }

        $this->artisan('helpdesk:unlock-login', ['login' => 'lock.admin'])->expectsOutputToContain('Unlocked')->assertSuccessful();

        foreach ([self::STRANGER, self::OWNER] as $ip) {
            $this->assertFalse(RateLimiter::tooManyAttempts(LoginThrottle::accountKey('lock.admin', $ip), LoginThrottle::MAX_ATTEMPTS));
        }
        $this->assertDatabaseHas('system_logs', ['event_type' => 'auth.lockout_cleared']);
    }

    public function test_the_unlock_command_says_so_when_nothing_is_locked(): void
    {
        $this->adminWithPassword();

        $this->artisan('helpdesk:unlock-login', ['login' => 'lock.admin'])->expectsOutputToContain('not locked')->assertSuccessful();
    }
}
