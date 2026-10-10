<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class StaffRememberMeLimitTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function rememberCookieDays(User $user, bool $remember): ?float
    {
        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'password123',
            'remember' => $remember ? '1' : '0',
        ]);

        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                return ($cookie->getExpiresTime() - time()) / 86400;
            }
        }

        return null;
    }

    public function test_staff_stay_signed_in_for_at_most_sixty_days_when_they_tick_remember_me(): void
    {
        foreach ([User::ROLE_TECHNICAL, User::ROLE_SUPER_USER, User::ROLE_ADMIN] as $role) {
            $staff = $this->createUser("Staff {$role}", "remember-{$role}@example.com", $role);

            $days = $this->rememberCookieDays($staff, true);

            $this->assertNotNull($days, "No remember-me cookie for {$role}.");
            $this->assertEqualsWithDelta(60, $days, 1, "{$role} must be limited to 60 days.");

            auth()->logout();
        }
    }

    public function test_no_remember_cookie_is_set_when_the_box_is_not_ticked(): void
    {
        $staff = $this->createUser('Staff No Box', 'remember-nobox@example.com', User::ROLE_TECHNICAL);

        $this->assertNull($this->rememberCookieDays($staff, false));
    }

    public function test_clients_keep_the_default_remember_me_length(): void
    {
        $client = $this->createClient('Remember Client', 'remember-client@example.com');

        $days = $this->rememberCookieDays($client, true);

        $this->assertNotNull($days);
        $this->assertGreaterThan(300, $days);
    }
}
